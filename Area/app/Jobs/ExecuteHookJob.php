<?php

namespace App\Jobs;

use App\Models\Area;
use App\Services\ServiceFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteHookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('ExecuteHookJob started');

        // Fetch active AREAs with relationships
        $areas = Area::where('is_active', true)
            ->with(['action.service', 'reaction.service', 'user'])
            ->get();

        foreach ($areas as $area) {
            try {
                $this->processArea($area);
            } catch (\Exception $e) {
                Log::error("Error processing Area {$area->id}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info('ExecuteHookJob completed', ['areas_processed' => $areas->count()]);
    }

    /**
     * Process a single AREA
     */
    protected function processArea(Area $area): void
    {
        $actionServiceName = $area->action?->service?->name ?? '';
        $reactionServiceName = $area->reaction?->service?->name ?? '';
        $actionName = $area->action?->name ?? '';
        $reactionName = $area->reaction?->name ?? '';
        $userId = $area->user_id;

        if (empty($actionServiceName) || empty($reactionServiceName)) {
            Log::warning("Area {$area->id}: Missing service configuration");
            return;
        }

        // Check if action service exists
        if (!ServiceFactory::exists($actionServiceName)) {
            Log::warning("Area {$area->id}: Unknown action service: {$actionServiceName}");
            return;
        }

        // Create action service and check trigger
        $actionService = ServiceFactory::create($actionServiceName);
        $actionResult = $actionService->checkAction(
            $actionName,
            $area->action_params ?? [],
            $area->last_executed_at,
            $userId
        );

        // If action triggered, execute reaction
        if ($actionResult !== false && is_array($actionResult)) {
            Log::info("Area {$area->id}: Action triggered", [
                'action' => $actionName,
                'service' => $actionServiceName,
            ]);

            // Check if reaction service exists
            if (!ServiceFactory::exists($reactionServiceName)) {
                Log::warning("Area {$area->id}: Unknown reaction service: {$reactionServiceName}");
                return;
            }

            // Create reaction service and execute
            $reactionService = ServiceFactory::create($reactionServiceName);
            
            // Merge reaction params with action data for dynamic placeholders
            $reactionParams = array_merge(
                $area->reaction_params ?? [],
                ['action_data' => $actionResult]
            );
            
            // If reaction params has a message placeholder, use action's message
            if (isset($reactionParams['message']) && strpos($reactionParams['message'], '{message}') !== false) {
                $reactionParams['message'] = str_replace(
                    '{message}',
                    $actionResult['message'] ?? '',
                    $reactionParams['message']
                );
            } elseif (!isset($reactionParams['message']) && isset($actionResult['message'])) {
                // Use action's default message if no custom message
                $reactionParams['message'] = $actionResult['message'];
            }

            $reactionResult = $reactionService->executeReaction(
                $reactionName,
                $reactionParams,
                $actionResult
            );

            Log::info("Area {$area->id}: Reaction executed", [
                'reaction' => $reactionName,
                'service' => $reactionServiceName,
                'result' => $reactionResult,
            ]);

            // Update last execution time
            $area->update(['last_executed_at' => now()]);

            // TODO: Record to trigger history
        }
    }
}
