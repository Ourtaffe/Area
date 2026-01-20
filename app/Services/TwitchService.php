<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\UserService;

class TwitchService implements ServiceInterface
{
    private $clientId;
    private $clientSecret;
    private $accessToken;
    
    public function __construct()
    {
        $this->clientId = config('services.twitch.client_id', env('TWITCH_CLIENT_ID'));
        $this->clientSecret = config('services.twitch.client_secret', env('TWITCH_CLIENT_SECRET'));
        $this->accessToken = $this->getAccessToken();
    }
    
    /**
     * Obtenir un token d'accès Twitch avec gestion d'erreur robuste
     */
    private function getAccessToken(): string
    {
        // Si pas de credentials, retourner vide
        if (empty($this->clientId) || empty($this->clientSecret)) {
            Log::warning('Twitch credentials missing in .env');
            return '';
        }
        
        // Token en cache
        $cachedToken = cache('twitch_access_token');
        if ($cachedToken) {
            return $cachedToken;
        }
        
        // Obtenir un nouveau token
        try {
            $response = Http::timeout(10)
                ->retry(3, 100)
                ->asForm()
                ->post('https://id.twitch.tv/oauth2/token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials'
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $token = $data['access_token'];
                $expiresIn = $data['expires_in'] ?? 3600;
                
                // Cache avec marge de sécurité
                cache(['twitch_access_token' => $token], now()->addSeconds($expiresIn - 300));
                
                Log::info('Twitch access token obtained successfully');
                return $token;
            } else {
                Log::error('Twitch token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return '';
            }
            
        } catch (\Exception $e) {
            Log::error('Twitch token exception: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Vérifier les actions Twitch
     */
    public function checkAction(string $actionName, array $params, ?Carbon $lastExecutedAt, ?int $userId = null): array|false
    {
        // Si pas de token, log et retourner false
        if (empty($this->accessToken)) {
            Log::error('TwitchService: No access token available');
            return false;
        }
        
        switch ($actionName) {
            case 'twitch_stream_online':
                return $this->checkStreamOnline($params['streamer_name'], $lastExecutedAt);
                
            case 'twitch_new_follower':
                return $this->checkNewFollowers($params['streamer_name'], $lastExecutedAt, $userId);
                
            default:
                Log::warning("TwitchService: Unknown action {$actionName}");
                return false;
        }
    }
    
    /**
     * Vérifier si un streamer est en ligne (API RÉELLE)
     */
    private function checkStreamOnline(string $streamerName, ?Carbon $lastCheck): array|false
    {
        try {
            // 1. Obtenir l'ID du streamer
            $streamerId = $this->getUserIdByLogin($streamerName);
            
            if (!$streamerId) {
                Log::warning("TwitchService: Streamer '{$streamerName}' not found");
                return false;
            }
            
            // 2. Vérifier le stream
            $response = Http::withHeaders([
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $this->accessToken,
            ])
            ->timeout(10)
            ->get('https://api.twitch.tv/helix/streams', [
                'user_id' => $streamerId,
                'first' => 1
            ]);
            
            if (!$response->successful()) {
                Log::error('Twitch API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'streamer' => $streamerName
                ]);
                return false;
            }
            
            $data = $response->json();
            $streams = $data['data'] ?? [];
            
            // 3. Si le streamer est en ligne
            if (!empty($streams)) {
                $stream = $streams[0];
                $startedAt = Carbon::parse($stream['started_at']);
                
                // 4. Vérifier si c'est un nouveau stream
                if (!$lastCheck || $startedAt > $lastCheck) {
                    return [
                        'triggered' => true,
                        'data' => [
                            'streamer_name' => $streamerName,
                            'streamer_login' => $stream['user_login'],
                            'stream_title' => $stream['title'] ?? 'No title',
                            'game_name' => $stream['game_name'] ?? 'Just Chatting',
                            'viewer_count' => $stream['viewer_count'] ?? 0,
                            'started_at' => $startedAt->toISOString(),
                            'started_at_human' => $startedAt->diffForHumans(),
                            'thumbnail_url' => $this->formatThumbnailUrl($stream['thumbnail_url'] ?? ''),
                            'url' => 'https://twitch.tv/' . $stream['user_login'],
                            'game_art' => $this->getGameArt($stream['game_id'] ?? ''),
                            'message' => $this->generateMessage($streamerName, $stream)
                        ]
                    ];
                }
                
                Log::info("Twitch: {$streamerName} is live but already notified");
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Twitch checkStreamOnline error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Formater l'URL de thumbnail
     */
    private function formatThumbnailUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }
        return str_replace('{width}x{height}', '640x360', $url);
    }
    
    /**
     * Obtenir l'art du jeu
     */
    private function getGameArt(?string $gameId): string
    {
        if (!$gameId) return '';
        
        try {
            $response = Http::withHeaders([
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $this->accessToken,
            ])->get('https://api.twitch.tv/helix/games', [
                'id' => $gameId
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['data'][0]['box_art_url'])) {
                    return str_replace('{width}x{height}', '285x380', $data['data'][0]['box_art_url']);
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }
        
        return '';
    }
    
    /**
     * Générer un message formaté
     */
    private function generateMessage(string $streamerName, array $stream): string
    {
        $viewers = number_format($stream['viewer_count'] ?? 0);
        $game = $stream['game_name'] ?? 'Just Chatting';
        
        return "🔴 **{$streamerName} est en live sur Twitch!**\n" .
               "🎮 **Jeu:** {$game}\n" .
               "👁️ **Viewers:** {$viewers}\n" .
               "📺 **Titre:** {$stream['title']}\n" .
               "⏰ **Commencé:** " . Carbon::parse($stream['started_at'])->diffForHumans();
    }
    
    /**
     * Obtenir l'ID utilisateur Twitch
     */
    private function getUserIdByLogin(string $login): ?string
    {
        try {
            $response = Http::withHeaders([
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $this->accessToken,
            ])
            ->timeout(5)
            ->get('https://api.twitch.tv/helix/users', [
                'login' => $login
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['data'][0]['id'])) {
                    return $data['data'][0]['id'];
                }
            }
        } catch (\Exception $e) {
            Log::error('Twitch getUserById error: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Vérifier les nouveaux followers
     */
    private function checkNewFollowers(string $streamerName, ?Carbon $lastCheck, ?int $userId): array|false
    {
        // Pour l'instant, retourner false (nécessite OAuth streamer)
        return false;
    }
    
    /**
     * Exécuter une réaction
     */
    public function executeReaction(string $reactionName, array $params, array $actionData = []): array
    {
        return [
            'success' => false,
            'message' => 'Twitch ne supporte pas les réactions'
        ];
    }
    
    /**
     * Tester la connexion à l'API
     */
    public function testConnection(): bool
    {
        try {
            $token = $this->getAccessToken();
            
            if (empty($token)) {
                Log::error('Twitch: No access token - check credentials in .env');
                return false;
            }
            
            // Tester avec une requête simple
            $response = Http::withHeaders([
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $token,
            ])
            ->timeout(5)
            ->get('https://api.twitch.tv/helix/games/top', [
                'first' => 1
            ]);
            
            $success = $response->successful();
            
            if (!$success) {
                Log::error('Twitch API test failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
            
            return $success;
            
        } catch (\Exception $e) {
            Log::error('Twitch connection test exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Méthode de DEBUG : Vérifier les credentials
     */
    public function debugCredentials(): array
    {
        return [
            'has_client_id' => !empty($this->clientId),
            'has_client_secret' => !empty($this->clientSecret),
            'client_id_length' => strlen($this->clientId ?? ''),
            'client_secret_length' => strlen($this->clientSecret ?? ''),
            'has_access_token' => !empty($this->accessToken),
            'token_prefix' => substr($this->accessToken ?? '', 0, 10) . '...'
        ];
    }
}