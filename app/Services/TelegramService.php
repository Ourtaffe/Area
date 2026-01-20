<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TelegramService implements ServiceInterface
{
    public function checkAction(string $actionName, array $params, ?Carbon $lastCheck, ?int $userId = null): array|false
    {
        // Pour l'instant, Telegram n'a que des réactions
        return false;
    }
    
    public function executeReaction(string $reactionName, array $params, array $actionData = []): array
    {
        switch ($reactionName) {
            case 'telegram_send_message':
                return $this->sendMessage($params, $actionData);
            case 'telegram_send_photo':
                return $this->sendPhoto($params, $actionData);
            default:
                Log::warning("[TelegramService] Réaction inconnue: {$reactionName}");
                return [
                    'success' => false,
                    'message' => "Réaction inconnue: {$reactionName}"
                ];
        }
    }
    
    private function sendMessage(array $params, array $actionData = []): array
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $chatId = $params['chat_id'] ?? null;
        $message = $params['message'] ?? 'Notification AREA Bot';
        
        if (!$botToken || !$chatId) {
            Log::error('[TelegramService] Bot token ou chat_id manquant');
            return [
                'success' => false,
                'message' => 'Configuration Telegram manquante'
            ];
        }
        
        // Remplacer les variables dans le message
        $message = $this->replaceVariables($message, $actionData);
        
        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => false
            ]);
            
            if ($response->successful()) {
                Log::info('[TelegramService] Message envoyé avec succès');
                return [
                    'success' => true,
                    'message' => 'Message Telegram envoyé',
                    'data' => $response->json()
                ];
            } else {
                Log::error('[TelegramService] Erreur: ' . $response->body());
                return [
                    'success' => false,
                    'message' => 'Erreur Telegram API',
                    'error' => $response->json()
                ];
            }
        } catch (\Exception $e) {
            Log::error('[TelegramService] Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    private function sendPhoto(array $params, array $actionData = []): array
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $chatId = $params['chat_id'] ?? null;
        $photoUrl = $params['photo_url'] ?? null;
        $caption = $params['caption'] ?? '';
        
        if (!$botToken || !$chatId || !$photoUrl) {
            Log::error('[TelegramService] Paramètres manquants pour photo');
            return [
                'success' => false,
                'message' => 'Paramètres manquants'
            ];
        }
        
        // Remplacer les variables
        $caption = $this->replaceVariables($caption, $actionData);
        $photoUrl = $this->replaceVariables($photoUrl, $actionData);
        
        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                'chat_id' => $chatId,
                'photo' => $photoUrl,
                'caption' => $caption,
                'parse_mode' => 'HTML'
            ]);
            
            if ($response->successful()) {
                Log::info('[TelegramService] Photo envoyée avec succès');
                return [
                    'success' => true,
                    'message' => 'Photo Telegram envoyée',
                    'data' => $response->json()
                ];
            } else {
                Log::error('[TelegramService] Erreur photo: ' . $response->body());
                return [
                    'success' => false,
                    'message' => 'Erreur Telegram API',
                    'error' => $response->json()
                ];
            }
        } catch (\Exception $e) {
            Log::error('[TelegramService] Exception photo: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    private function replaceVariables(string $text, array $actionData): string
    {
        $variables = [
            '{{playlist_name}}' => $actionData['playlist_name'] ?? '',
            '{{total_new}}' => $actionData['total_new'] ?? '',
            '{{track_name}}' => $actionData['new_tracks'][0]['name'] ?? '',
            '{{artists}}' => $actionData['new_tracks'][0]['artists'] ?? '',
            '{{message}}' => $actionData['message'] ?? '',
            '{{video_title}}' => $actionData['video_title'] ?? '',
            '{{channel_name}}' => $actionData['channel_name'] ?? '',
            '{{temperature}}' => $actionData['temperature'] ?? '',
            '{{condition}}' => $actionData['condition'] ?? '',
        ];
        
        return str_replace(array_keys($variables), array_values($variables), $text);
    }
}
