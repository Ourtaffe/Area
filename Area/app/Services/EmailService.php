<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

// CHANGE: Enlève "use App\Interfaces\ServiceInterface" et utilise directement ServiceInterface du même namespace
class EmailService implements ServiceInterface
{
    public function checkAction(string $actionName, array $params, ?Carbon $lastExecutedAt, ?int $userId = null): array|false
    {
        return false;
    }

    public function executeReaction(string $reactionName, array $params, array $actionData = []): array
    {
        Log::info('[EmailService] Exécution réaction: ' . $reactionName);
        
        try {
            // Récupère le destinataire
            $toEmail = $params['to_email'] ?? $params['to'] ?? null;
            if (!$toEmail) {
                throw new \Exception('Adresse email du destinataire manquante');
            }
            
            $subject = $params['subject'] ?? 'Notification AREA';
            $body = $params['body'] ?? 'Pas de contenu';
            
            // Remplacer variables
            foreach ($actionData as $key => $value) {
                if (is_scalar($value)) {
                    $subject = str_replace(['{{' . $key . '}}', '{{ ' . $key . ' }}'], (string)$value, $subject);
                    $body = str_replace(['{{' . $key . '}}', '{{ ' . $key . ' }}'], (string)$value, $body);
                }
            }
            
            // Variables globales
            $subject = str_replace(['{{timestamp}}', '{{date}}', '{{time}}'], 
                [now()->format('H:i:s'), now()->format('Y-m-d'), now()->format('H:i:s')], $subject);
            $body = str_replace(['{{timestamp}}', '{{date}}', '{{time}}'], 
                [now()->format('Y-m-d H:i:s'), now()->format('Y-m-d'), now()->format('H:i:s')], $body);
            
            // Envoi
            Mail::raw($body, function($message) use ($toEmail, $subject) {
                $message->to($toEmail)
                        ->subject($subject)
                        ->from(config('mail.from.address', 'noreply@area-bot.com'), 
                               config('mail.from.name', 'AREA Bot'));
            });
            
            Log::info('[EmailService] Email envoyé avec succès à: ' . $toEmail);
            
            return [
                'success' => true,
                'message' => 'Email envoyé à ' . $toEmail,
                'data' => [
                    'to' => $toEmail,
                    'subject' => $subject
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('[EmailService] Erreur: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email: ' . $e->getMessage()
            ];
        }
    }
}