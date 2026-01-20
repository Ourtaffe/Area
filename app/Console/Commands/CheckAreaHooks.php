<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Services\ServiceFactory;
use Illuminate\Console\Command;

class CheckAreaHooks extends Command
{
    protected $signature = 'area:check-hooks';
    protected $description = 'Check all active areas for triggers';

    public function handle()
    {
        $this->info(' Checking active areas...');
        
        $areas = Area::where('is_active', true)
            ->with(['action.service', 'reaction.service'])
            ->get();
        
        $this->line("Found {$areas->count()} active area(s)");
        
        foreach ($areas as $area) {
            try {
                $this->line("\n Checking area #{$area->id}: {$area->name}");
                
                // Récupérer le service d'action
                $serviceName = $area->action->service->name;
                $service = ServiceFactory::create($serviceName);
                
                if (!$service) {
                    $this->error("    Service not found: {$serviceName}");
                    continue;
                }
                
                // Décoder les paramètres d'action
                $actionParams = json_decode($area->action_params ?? '{}', true) ?? [];
                
                // Vérifier si l'action doit être déclenchée
                $result = $service->checkAction(
                    $area->action->identifier,
                    $actionParams,
                    $area->last_executed_at,
                    $area->user_id
                );
                
                if ($result === false) {
                    $this->line("   ⏭ No trigger (no new data)");
                } else {
                    $this->info("    Triggered! Found new data");
                    
                    // Afficher les données trouvées
                    $data = $result['data'] ?? $result;
                    if (isset($data['message'])) {
                        $this->line("   📊 Found: {$data['message']}");
                    }
                    
                    // EXÉCUTER LA RÉACTION
                    $this->executeReaction($area, $data);
                    
                    // Mettre à jour la date d'exécution
                    $area->update(['last_executed_at' => now()]);
                    $this->line("    Updated last_executed_at");
                }
                
            } catch (\Exception $e) {
                $this->error("    Error: " . $e->getMessage());
            }
        }
        
        $this->info("\n Check completed!");
    }
    
    /**
     * Exécute la réaction associée à l'area
     */
    private function executeReaction(Area $area, array $actionData): void
    {
        try {
            $reactionServiceName = $area->reaction->service->name;
            $this->line("    Executing reaction: {$reactionServiceName}");
            
            $reactionService = ServiceFactory::create($reactionServiceName);
            
            if (!$reactionService) {
                $this->error("    Reaction service not found: {$reactionServiceName}");
                return;
            }
            
            // Décoder les paramètres de réaction
            $reactionParams = json_decode($area->reaction_params ?? '{}', true) ?? [];
            
            // Préparer le message avec les variables
            if (isset($reactionParams['message'])) {
                $message = $reactionParams['message'];
                
                // Remplacer les variables {{variable}} par les données
                foreach ($actionData as $key => $value) {
                    if (is_string($value) || is_numeric($value)) {
                        $message = str_replace("{{{$key}}}", $value, $message);
                    }
                }
                
                // Remplacer aussi les variables imbriquées
                if (isset($actionData['stars']) && is_array($actionData['stars']) && !empty($actionData['stars'])) {
                    $lastStar = $actionData['stars'][0];
                    $message = str_replace('{{user}}', $lastStar['user'] ?? 'someone', $message);
                    $message = str_replace('{{repo}}', $actionData['repo'] ?? 'repository', $message);
                }
                
                $reactionParams['message'] = $message;
                $this->line("    Message: {$message}");
            }
            
            // Exécuter la réaction
            $result = $reactionService->executeReaction(
                $area->reaction->identifier,
                $reactionParams,
                $actionData
            );
            
            if ($result['success'] ?? false) {
                $this->info("   Reaction executed successfully");
            } else {
                $this->error("   Reaction failed: " . ($result['message'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            $this->error("   Reaction execution error: " . $e->getMessage());
        }
    }
}