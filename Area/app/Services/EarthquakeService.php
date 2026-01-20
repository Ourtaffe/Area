<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EarthquakeService implements ServiceInterface
{
    protected string $baseUrl = 'https://earthquake.usgs.gov/fdsnws/event/1';

    /**
     * Check Earthquake/USGS actions
     */
    public function checkAction(string $actionName, array $params, ?Carbon $lastExecutedAt, ?int $userId = null): array|false
    {
        return match ($actionName) {
            'earthquake_magnitude' => $this->checkEarthquake($params, $lastExecutedAt, $userId),
            default => false,
        };
    }

    /**
     * USGS doesn't have reactions
     */
    public function executeReaction(string $reactionName, array $params, array $actionData = []): array
    {
        return ['success' => false, 'error' => 'Earthquake reactions not implemented'];
    }

    /**
     * Check for earthquakes above magnitude threshold
     */
    protected function checkEarthquake(array $params, ?Carbon $lastExecutedAt, ?int $userId): array|false
    {
        $minMagnitude = $params['min_magnitude'] ?? 5.0;
        $cacheKey = "earthquake_latest_{$userId}_{$minMagnitude}";

        try {
            // Query for recent earthquakes
            $startTime = $lastExecutedAt?->toIso8601String() ?? now()->subHour()->toIso8601String();
            
            $response = Http::get("{$this->baseUrl}/query", [
                'format' => 'geojson',
                'starttime' => $startTime,
                'minmagnitude' => $minMagnitude,
                'orderby' => 'time',
                'limit' => 10,
            ]);

            if (!$response->successful()) {
                Log::error('USGS: API error', ['status' => $response->status()]);
                return false;
            }

            $data = $response->json();
            $features = $data['features'] ?? [];

            if (count($features) === 0) {
                return false;
            }

            // Get previously seen earthquake ID
            $latestQuake = $features[0];
            $quakeId = $latestQuake['id'] ?? '';
            $previousQuakeId = Cache::get($cacheKey);

            Cache::put($cacheKey, $quakeId, now()->addHours(6));

            // First run - store and skip
            if ($previousQuakeId === null && $lastExecutedAt === null) {
                return false;
            }

            // Check if this is a new earthquake
            if ($quakeId !== $previousQuakeId) {
                $properties = $latestQuake['properties'] ?? [];
                $geometry = $latestQuake['geometry'] ?? [];
                $coordinates = $geometry['coordinates'] ?? [0, 0, 0];

                $magnitude = $properties['mag'] ?? 0;
                $place = $properties['place'] ?? 'Unknown location';
                $time = isset($properties['time']) 
                    ? Carbon::createFromTimestampMs($properties['time'])->toIso8601String()
                    : now()->toIso8601String();
                $url = $properties['url'] ?? '';

                return [
                    'triggered' => true,
                    'earthquake_id' => $quakeId,
                    'magnitude' => $magnitude,
                    'place' => $place,
                    'time' => $time,
                    'longitude' => $coordinates[0] ?? 0,
                    'latitude' => $coordinates[1] ?? 0,
                    'depth_km' => $coordinates[2] ?? 0,
                    'url' => $url,
                    'message' => "🌍 Séisme détecté ! Magnitude {$magnitude} - {$place}",
                ];
            }

            return false;
        } catch (\Exception $e) {
            Log::error('USGS: Exception', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
