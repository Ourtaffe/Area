<?php

namespace App\Services;

use App\Services\ServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SpotifyService implements ServiceInterface
{
    private $clientId;
    private $clientSecret;
    private $accessToken;
    private $tokenExpiry;
    
    public function __construct()
    {
        $this->clientId = env('SPOTIFY_CLIENT_ID');
        $this->clientSecret = env('SPOTIFY_CLIENT_SECRET');
        $this->accessToken = null;
        $this->tokenExpiry = null;
    }
    
    private function ensureToken(): bool
    {
        // Si le token est encore valide
        if ($this->accessToken && $this->tokenExpiry && $this->tokenExpiry->greaterThan(now()->addMinute())) {
            return true;
        }
        
        return $this->authenticate();
    }
    
    private function authenticate(): bool
    {
        try {
            $response = Http::asForm()
                ->withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                ])
                ->post('https://accounts.spotify.com/api/token', [
                    'grant_type' => 'client_credentials'
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'];
                $this->tokenExpiry = now()->addSeconds($data['expires_in'] - 60);
                
                Log::info('[SpotifyService] Token obtenu avec succès');
                return true;
            } else {
                Log::error('[SpotifyService] Échec authentification: ' . $response->status());
                return false;
            }
        } catch (\Exception $e) {
            Log::error('[SpotifyService] Erreur authentification: ' . $e->getMessage());
            return false;
        }
    }
    
    public function checkAction(string $actionName, array $params, ?Carbon $lastCheck, ?int $userId = null): array|false
    {
        if (!$this->ensureToken()) {
            Log::error('[SpotifyService] Impossible d\'obtenir un token d\'accès');
            return false;
        }
        
        switch ($actionName) {
            case 'spotify_new_track':
                return $this->checkPlaylistNewTrack($params, $lastCheck);
            case 'spotify_new_playlist':
                return $this->checkNewPlaylist($params, $lastCheck);
            default:
                Log::warning("[SpotifyService] Action inconnue: {$actionName}");
                return false;
        }
    }
    
    private function checkPlaylistNewTrack(array $params, ?Carbon $lastCheck): array|false
    {
        $playlistId = $params['playlist_id'] ?? null;
        if (!$playlistId) {
            Log::error('[SpotifyService] playlist_id manquant');
            return false;
        }
        
        try {
            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->get("https://api.spotify.com/v1/playlists/{$playlistId}");
            
            if (!$response->successful()) {
                Log::error('[SpotifyService] Erreur API playlist: ' . $response->status());
                return false;
            }
            
            $playlistData = $response->json();
            
            // Récupère les tracks
            $tracksResponse = Http::withToken($this->accessToken)
                ->get("https://api.spotify.com/v1/playlists/{$playlistId}/tracks", [
                    'limit' => 50,
                    'fields' => 'items(added_at,track(name,artists(name),album(name),external_urls(spotify)))'
                ]);
            
            if (!$tracksResponse->successful()) {
                Log::error('[SpotifyService] Erreur API tracks: ' . $tracksResponse->status());
                return false;
            }
            
            $tracksData = $tracksResponse->json();
            $tracks = $tracksData['items'] ?? [];
            
            // Si pas de dernière vérification, on ne déclenche pas
            if (!$lastCheck) {
                return false;
            }
            
            // Cherche les nouvelles chansons
            $newTracks = [];
            foreach ($tracks as $item) {
                $addedAt = Carbon::parse($item['added_at']);
                if ($addedAt->greaterThan($lastCheck)) {
                    $track = $item['track'];
                    $newTracks[] = [
                        'name' => $track['name'],
                        'artists' => implode(', ', array_column($track['artists'], 'name')),
                        'album' => $track['album']['name'],
                        'url' => $track['external_urls']['spotify'],
                        'added_at' => $addedAt->toDateTimeString(),
                    ];
                }
            }
            
            if (empty($newTracks)) {
                return false;
            }
            
            return [
                'triggered' => true,
                'data' => [
                    'playlist_id' => $playlistId,
                    'playlist_name' => $playlistData['name'] ?? 'Playlist inconnue',
                    'playlist_url' => $playlistData['external_urls']['spotify'] ?? '',
                    'playlist_image' => $playlistData['images'][0]['url'] ?? null,
                    'new_tracks' => $newTracks,
                    'total_new' => count($newTracks),
                    'message' => count($newTracks) . ' nouvelle(s) chanson(s) dans la playlist "' . ($playlistData['name'] ?? 'Unknown') . '"!'
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('[SpotifyService] Erreur: ' . $e->getMessage());
            return false;
        }
    }
    
    private function checkNewPlaylist(array $params, ?Carbon $lastCheck): array|false
    {
        $userId = $params['user_id'] ?? null;
        if (!$userId) {
            Log::error('[SpotifyService] user_id manquant');
            return false;
        }
        
        try {
            $response = Http::withToken($this->accessToken)
                ->get("https://api.spotify.com/v1/users/{$userId}/playlists", [
                    'limit' => 20
                ]);
            
            if (!$response->successful()) {
                Log::error('[SpotifyService] Erreur API utilisateur: ' . $response->status());
                return false;
            }
            
            $data = $response->json();
            $playlists = $data['items'] ?? [];
            
            if (!$lastCheck) {
                return false;
            }
            
            // Cherche les nouvelles playlists
            $newPlaylists = [];
            foreach ($playlists as $playlist) {
                $createdAt = Carbon::parse($playlist['created_at']);
                if ($createdAt->greaterThan($lastCheck)) {
                    $newPlaylists[] = [
                        'id' => $playlist['id'],
                        'name' => $playlist['name'],
                        'description' => $playlist['description'] ?? '',
                        'tracks_count' => $playlist['tracks']['total'],
                        'url' => $playlist['external_urls']['spotify'],
                        'image' => $playlist['images'][0]['url'] ?? null,
                        'created_at' => $createdAt->toDateTimeString(),
                    ];
                }
            }
            
            if (empty($newPlaylists)) {
                return false;
            }
            
            return [
                'triggered' => true,
                'data' => [
                    'user_id' => $userId,
                    'user_name' => $playlists[0]['owner']['display_name'] ?? $userId,
                    'new_playlists' => $newPlaylists,
                    'total_new' => count($newPlaylists),
                    'message' => count($newPlaylists) . ' nouvelle(s) playlist(s) créée(s) par ' . ($playlists[0]['owner']['display_name'] ?? $userId) . '!'
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('[SpotifyService] Erreur: ' . $e->getMessage());
            return false;
        }
    }
    
    public function executeReaction(string $reactionName, array $params, array $actionData = []): array
    {
        // Spotify n'a que des actions, pas de réactions
        return [
            'success' => false,
            'message' => 'SpotifyService n\'a pas de réactions'
        ];
    }
}
