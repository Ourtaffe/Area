<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TimerService implements ServiceInterface
{
    /**
     * Vérifier les actions de timer
     */
    public function checkAction(string $actionName, array $params, ?Carbon $lastExecutedAt, ?int $userId = null): array|false
    {
        switch ($actionName) {
            case 'timer_every_hour':
                return $this->checkEveryHour($lastExecutedAt, $params);
                
            case 'timer_every_day':
                return $this->checkEveryDay($lastExecutedAt, $params);
                
            case 'timer_specific_time':
                return $this->checkSpecificTime($lastExecutedAt, $params);
                
            case 'timer_every_x_minutes':
                return $this->checkEveryXMinutes($lastExecutedAt, $params);
                
            case 'timer_weekday':
                return $this->checkWeekday($lastExecutedAt, $params);
                
            default:
                Log::warning("TimerService: Unknown action {$actionName}");
                return false;
        }
    }
    
    /**
     * Toutes les heures (à HH:00)
     */
    private function checkEveryHour(?Carbon $lastCheck, array $params): array|false
    {
        $now = now();
        
        // Vérifier si on est à une heure pile (0 minutes)
        if ($now->minute === 0) {
            // Vérifier si on a déjà exécuté cette heure
            if (!$lastCheck || $lastCheck->hour !== $now->hour || $lastCheck->day !== $now->day) {
                return [
                    'triggered' => true,
                    'data' => [
                        'current_time' => $now->format('H:i'),
                        'hour' => $now->hour,
                        'message' => "🕐 Il est {$now->format('H:i')} ! Rappel horaire."
                    ]
                ];
            }
        }
        
        return false;
    }
    
    /**
     * Tous les jours à une heure spécifique
     */
 private function checkEveryDay(?Carbon $lastCheck, array $params): array|false
{
    $targetTime = $params['time'] ?? '09:00';
    $now = now();
    
    // DEBUG: Afficher pour vérifier
    Log::info("Timer checkEveryDay", [
        'target' => $targetTime,
        'current' => $now->format('H:i'),
        'match' => $now->format('H:i') === $targetTime,
        'lastCheck' => $lastCheck ? $lastCheck->format('Y-m-d H:i:s') : 'null'
    ]);
    
    // Vérifier si on est à l'heure cible (format 24h)
    if ($now->format('H:i') === $targetTime) {
        // Vérifier si on a déjà exécuté aujourd'hui
        if (!$lastCheck || $lastCheck->format('Y-m-d') !== $now->format('Y-m-d')) {
            return [
                'triggered' => true,
                'data' => [
                    'current_time' => $now->format('H:i'),
                    'target_time' => $targetTime,
                    'last_execution' => $lastCheck ? $lastCheck->format('Y-m-d H:i') : 'jamais',
                    'message' => "📅 Rappel quotidien à {$targetTime} !"
                ]
            ];
        }
    }
    
    return false;
}
    /**
     * Toutes les X minutes
     */
    private function checkEveryXMinutes(?Carbon $lastCheck, array $params): array|false
{
    $interval = $params['minutes'] ?? 30;
    $now = now();
    
    if (!$lastCheck) {
        // PREMIER DÉCLENCHEMENT
        return [
            'triggered' => true,
            'data' => [
                'interval' => $interval,
                'current_time' => $now->format('H:i'),
                'last_execution' => 'jamais',  // <-- AJOUTER CETTE LIGNE
                'minutes_since_last' => 0,
                'message' => "⏱️ Premier déclenchement toutes les {$interval} minutes"
            ]
        ];
    }
    
    $minutesDiff = $lastCheck->diffInMinutes($now);
    
    if ($minutesDiff >= $interval) {
        return [
            'triggered' => true,
            'data' => [
                'interval' => $interval,
                'minutes_since_last' => round($minutesDiff, 1),
                'current_time' => $now->format('H:i'),
                'last_execution' => $lastCheck->format('H:i'),  // <-- DÉJÀ PRÉSENT
                'message' => "⏱️ Déclenchement programmé toutes les {$interval} minutes"
            ]
        ];
    }
    
    return false;
}
    
    /**
     * Jour de semaine spécifique
     */
    private function checkWeekday(?Carbon $lastCheck, array $params): array|false
    {
        $targetDay = $params['day'] ?? 'monday'; // 0=dimanche, 1=lundi...
        $targetTime = $params['time'] ?? '09:00';
        
        $now = now();
        $currentDayName = strtolower($now->englishDayOfWeek);
        $targetDayName = strtolower($targetDay);
        
        // Vérifier jour et heure
        if ($currentDayName === $targetDayName && $now->format('H:i') === $targetTime) {
            if (!$lastCheck || $lastCheck->weekOfYear !== $now->weekOfYear) {
                return [
                    'triggered' => true,
                    'data' => [
                        'day' => ucfirst($targetDayName),
                        'time' => $targetTime,
                        'current_date' => $now->format('d/m/Y'),
                        'message' => "📅 C'est {$targetDayName} à {$targetTime} !"
                    ]
                ];
            }
        }
        
        return false;
    }
    
    /**
     * Heure spécifique (ponctuelle)
     */
    private function checkSpecificTime(?Carbon $lastCheck, array $params): array|false
    {
        $targetDateTime = $params['datetime'] ?? null;
        
        if (!$targetDateTime) {
            return false;
        }
        
        try {
            $target = Carbon::parse($targetDateTime);
            $now = now();
            
            // Vérifier si on est dans la même minute que la cible
            if ($now->format('Y-m-d H:i') === $target->format('Y-m-d H:i')) {
                if (!$lastCheck || $lastCheck->format('Y-m-d H:i') !== $target->format('Y-m-d H:i')) {
                    return [
                        'triggered' => true,
                        'data' => [
                            'target_datetime' => $target->format('d/m/Y H:i'),
                            'current_time' => $now->format('H:i'),
                            'message' => "🎯 C'est l'heure programmée : {$target->format('d/m/Y H:i')} !"
                        ]
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('TimerService: Invalid datetime format', ['datetime' => $targetDateTime]);
        }
        
        return false;
    }
    
    /**
     * Exécuter une réaction (Timer ne fait que déclencher)
     */
    public function executeReaction(string $reactionName, array $params, array $actionData = []): array
    {
        return [
            'success' => false,
            'message' => 'TimerService ne supporte pas les réactions'
        ];
    }
    
    /**
     * Tester la connexion (toujours vrai pour Timer)
     */
    public function testConnection(): bool
    {
        return true;
    }
}