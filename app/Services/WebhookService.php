<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService implements ServiceInterface
{
   
    public function checkAction(string $actionName, array $params, ?Carbon $lastExecutedAt, ?int $userId = null): array|false
    {
       
        return false;
    }

   
    public function executeReaction(string $reactionName, array $params, array $actionData = []): array
    {
        return match ($reactionName) {
            'webhook_call' => $this->callWebhook($params, $actionData),
            default => ['success' => false, 'error' => 'Unknown reaction: ' . $reactionName],
        };
    }

    protected function callWebhook(array $params, array $actionData = []): array
    {
        $url = $params['url'] ?? null;
        $method = strtoupper($params['method'] ?? 'POST');
        $headers = $params['headers'] ?? [];
        $body = $params['body'] ?? [];

        if (empty($url)) {
            Log::error('Webhook: No URL provided');
            return ['success' => false, 'error' => 'No URL provided'];
        }

        if (is_array($body)) {
            array_walk_recursive($body, function (&$value) use ($actionData) {
                if (is_string($value)) {
                    foreach ($actionData as $key => $data) {
                        if (is_string($data) || is_numeric($data)) {
                            $value = str_replace('{' . $key . '}', (string) $data, $value);
                        }
                    }
                }
            });
        }

        try {
            $request = Http::withHeaders($headers);

            $response = match ($method) {
                'GET' => $request->get($url, $body),
                'POST' => $request->post($url, $body),
                'PUT' => $request->put($url, $body),
                'PATCH' => $request->patch($url, $body),
                'DELETE' => $request->delete($url, $body),
                default => $request->post($url, $body),
            };

            if ($response->successful()) {
                Log::info('Webhook: Call successful', ['url' => $url]);
                return [
                    'success' => true,
                    'status' => $response->status(),
                    'response' => $response->json() ?? $response->body(),
                ];
            }

            Log::error('Webhook: Call failed', ['url' => $url, 'status' => $response->status()]);
            return [
                'success' => false,
                'error' => 'HTTP ' . $response->status(),
                'response' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Webhook: Exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
