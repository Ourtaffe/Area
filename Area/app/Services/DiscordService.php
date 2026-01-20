<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordService implements ServiceInterface
{
    /**
     * Vérifier une action (Discord ne fait que des réactions)
     */
    public function checkAction(string $actionName, array $params, ?\Carbon\Carbon $lastExecutedAt, ?int $userId = null): array|false
    {
        // DiscordService ne supporte pas les actions, seulement les réactions
        return false;
    }

    /**
     * Exécuter une réaction Discord - VERSION COMPLÈTEMENT CORRIGÉE
     */
    public function executeReaction(string $reactionName, array $params, array $actionData = []): array
    {
        try {
            Log::info('DiscordService: Executing reaction', [
                'reaction' => $reactionName,
                'has_webhook' => !empty($params['webhook_url'])
            ]);

            // Vérifier l'URL webhook
            if (empty($params['webhook_url'])) {
                Log::error('DiscordService: Webhook URL is required');
                return [
                    'success' => false,
                    'message' => 'Webhook URL is required for Discord reaction'
                ];
            }

            // Préparer toutes les données disponibles
            $allVariables = $this->extractAllVariables($actionData);
            Log::info('DiscordService: Variables disponibles', array_keys($allVariables));

            // Préparer le payload Discord
            $payload = [
                'username' => $params['username'] ?? 'AREA Bot',
                'avatar_url' => $params['avatar_url'] ?? null,
            ];

            // 1. TRAITER LE MESSAGE SIMPLE
            if (isset($params['message'])) {
                $message = $params['message'];
                
                // Remplacer les variables dans le message
                $message = $this->replaceVariables($message, $allVariables);
                
                // Convertir \n en vraies nouvelles lignes
                $message = $this->formatMessageForDiscord($message);
                
                $payload['content'] = $message;
                Log::info('DiscordService: Message préparé', ['message_preview' => substr($message, 0, 100)]);
            }

            // 2. TRAITER LES EMBEDS
            if (isset($params['embeds']) && is_array($params['embeds'])) {
                $embeds = $params['embeds'];
                
                // Remplacer les variables dans les embeds
                $embeds = $this->replaceVariablesInEmbeds($embeds, $allVariables);
                
                $payload['embeds'] = $embeds;
                Log::info('DiscordService: Embeds préparés', ['count' => count($embeds)]);
            }

            // 3. ENVOYER À DISCORD
            Log::info('DiscordService: Sending to Discord', [
                'webhook_url' => substr($params['webhook_url'], 0, 50) . '...'
            ]);

            $response = Http::timeout(15)
                ->retry(2, 1000)
                ->post($params['webhook_url'], $payload);

            Log::info('DiscordService: Discord response', [
                'status' => $response->status(),
                'success' => $response->successful()
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Message sent to Discord successfully',
                    'data' => [
                        'status' => $response->status(),
                        'timestamp' => now()->toDateTimeString()
                    ]
                ];
            } else {
                $errorBody = $response->body();
                Log::error('DiscordService: Failed to send', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                    'payload_preview' => json_encode($payload, JSON_PRETTY_PRINT)
                ]);

                // Essayer de comprendre l'erreur
                $errorMessage = 'Failed to send to Discord: HTTP ' . $response->status();
                
                try {
                    $errorJson = json_decode($errorBody, true);
                    if (isset($errorJson['message'])) {
                        $errorMessage .= ' - ' . $errorJson['message'];
                    }
                    if (isset($errorJson['embeds'])) {
                        $errorMessage .= ' (Erreur dans les embeds)';
                    }
                } catch (\Exception $e) {
                    // Ignorer si pas JSON
                }

                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'error_details' => $errorBody
                ];
            }

        } catch (\Exception $e) {
            Log::error('DiscordService exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'DiscordService error: ' . $e->getMessage(),
                'exception' => get_class($e)
            ];
        }
    }

    /**
     * Extraire toutes les variables disponibles depuis les données d'action
     */
    private function extractAllVariables(array $actionData): array
    {
        $allVariables = [];

        // Récupérer toutes les données plates
        foreach ($actionData as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $allVariables[$key] = $value;
            }
        }

        // Récupérer les données dans 'data' si elles existent
        if (isset($actionData['data']) && is_array($actionData['data'])) {
            foreach ($actionData['data'] as $key => $value) {
                if (is_scalar($value) || is_null($value)) {
                    $allVariables[$key] = $value;
                }
            }
        }

        // Ajouter des variables calculées
        $allVariables['timestamp'] = now()->format('Y-m-d H:i');
        $allVariables['current_time'] = now()->format('H:i');
        $allVariables['current_date'] = now()->format('Y-m-d');

        // Calcul spécial pour hours_ago si on a starred_at
        if (!isset($allVariables['hours_ago'])) {
            $allVariables['hours_ago'] = $this->calculateHoursAgo($actionData);
        }

        // Nettoyer les valeurs null
        $allVariables = array_filter($allVariables, function ($value) {
            return $value !== null;
        });

        return $allVariables;
    }

    /**
     * Calculer hours_ago depuis starred_at
     */
    private function calculateHoursAgo(array $actionData): string
    {
        $starredAt = null;

        // Chercher starred_at dans différentes structures
        if (isset($actionData['stars'][0]['starred_at'])) {
            $starredAt = $actionData['stars'][0]['starred_at'];
        } elseif (isset($actionData['starred_at'])) {
            $starredAt = $actionData['starred_at'];
        } elseif (isset($actionData['data']['stars'][0]['starred_at'])) {
            $starredAt = $actionData['data']['stars'][0]['starred_at'];
        } elseif (isset($actionData['data']['starred_at'])) {
            $starredAt = $actionData['data']['starred_at'];
        }

        if ($starredAt) {
            try {
                $starredTime = \Carbon\Carbon::parse($starredAt);
                $hoursAgo = $starredTime->diffInHours(now());
                
                if ($hoursAgo == 0) {
                    $minutesAgo = $starredTime->diffInMinutes(now());
                    return $minutesAgo > 1 ? "$minutesAgo minutes" : "quelques minutes";
                }
                
                return "$hoursAgo heures";
            } catch (\Exception $e) {
                Log::warning('DiscordService: Failed to parse starred_at', ['starred_at' => $starredAt]);
            }
        }

        return 'quelques';
    }

    /**
     * Remplacer les variables dans une chaîne
     */
    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            if (is_scalar($value)) {
                $search = '{{' . $key . '}}';
                if (strpos($text, $search) !== false) {
                    $text = str_replace($search, (string)$value, $text);
                }
            }
        }
        
        return $text;
    }

    /**
     * Remplacer les variables dans les embeds (récursif)
     */
    private function replaceVariablesInEmbeds(array $embeds, array $variables): array
    {
        array_walk_recursive($embeds, function (&$item, $key) use ($variables) {
            if (is_string($item)) {
                $item = $this->replaceVariables($item, $variables);
            }
        });

        return $embeds;
    }

    /**
     * Formater le message pour Discord
     */
    private function formatMessageForDiscord(string $message): string
    {
        // Convertir toutes les formes de nouvelles lignes
        $message = str_replace(['\\n', '\\\\n', '\n'], "\n", $message);
        
        // Nettoyer les espaces en début/fin de ligne
        $lines = explode("\n", $message);
        $lines = array_map('trim', $lines);
        
        // Supprimer les lignes vides multiples
        $cleanedLines = [];
        $previousWasEmpty = false;
        
        foreach ($lines as $line) {
            if (empty($line)) {
                if (!$previousWasEmpty) {
                    $cleanedLines[] = '';
                    $previousWasEmpty = true;
                }
            } else {
                $cleanedLines[] = $line;
                $previousWasEmpty = false;
            }
        }
        
        return implode("\n", $cleanedLines);
    }

    /**
     * Tester la connexion Discord
     */
    public function testConnection(): bool
    {
        Log::info('DiscordService: Testing connection');
        
        // Test avec un webhook de test
        $testWebhook = 'https://discord.com/api/webhooks/1460971436758929451/F0IL7fskS3G_ICVTFhnhQR79VgTaST71v4YRjGUZYuFtyb36R16LZD61y3L6uptosgio';
        
        try {
            $response = Http::timeout(10)
                ->post($testWebhook, [
                    'content' => '🧪 Test de connexion DiscordService - ' . now()->format('H:i:s'),
                    'username' => 'AREA Test Bot'
                ]);
            
            if ($response->successful()) {
                Log::info('DiscordService: Connection test successful');
                return true;
            } else {
                Log::error('DiscordService: Connection test failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('DiscordService: Connection test exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Méthode helper pour debug
     */
    public function debugVariables(array $actionData): array
    {
        $variables = $this->extractAllVariables($actionData);
        
        // Formater pour l'affichage
        $formatted = [];
        foreach ($variables as $key => $value) {
            if (is_string($value) && strlen($value) > 50) {
                $formatted[$key] = substr($value, 0, 50) . '...';
            } else {
                $formatted[$key] = $value;
            }
        }
        
        return $formatted;
    }
}