<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubAuthController extends Controller
{
    /**
     * Rediriger vers GitHub OAuth
     */
    public function redirect(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);
        
        $state = bin2hex(random_bytes(16));
        session(['github_oauth_state' => $state]);
        session(['github_oauth_user_id' => $request->user_id]);
        
        $scopes = 'repo read:user notifications';
        
        $url = 'https://github.com/login/oauth/authorize?' . http_build_query([
            'client_id' => config('services.github.client_id'),
            'redirect_uri' => config('services.github.redirect'),
            'scope' => $scopes,
            'state' => $state,
            'allow_signup' => 'true'
        ]);
        
        return response()->json([
            'success' => true,
            'url' => $url
        ]);
    }
    
    /**
     * Callback GitHub OAuth
     */
    public function callback(Request $request)
    {
        $code = $request->input('code');
        $state = $request->input('state');
        
        // Vérifier le state
        if (!$state || $state !== session('github_oauth_state')) {
            Log::error('GitHub OAuth state mismatch');
            return response()->json(['error' => 'Invalid state'], 400);
        }
        
        $userId = session('github_oauth_user_id');
        
        // Échanger le code contre un token
        $response = Http::withHeaders([
            'Accept' => 'application/json'
        ])->post('https://github.com/login/oauth/access_token', [
            'client_id' => config('services.github.client_id'),
            'client_secret' => config('services.github.client_secret'),
            'code' => $code,
            'redirect_uri' => config('services.github.redirect'),
            'state' => $state
        ]);
        
        if (!$response->successful()) {
            Log::error('GitHub token exchange failed', ['response' => $response->body()]);
            return response()->json(['error' => 'Token exchange failed'], 400);
        }
        
        $data = $response->json();
        $accessToken = $data['access_token'] ?? null;
        
        if (!$accessToken) {
            return response()->json(['error' => 'No access token received'], 400);
        }
        
        // Récupérer les infos utilisateur GitHub
        $userResponse = Http::withToken($accessToken)
            ->get('https://api.github.com/user');
        
        $githubUser = $userResponse->json();
        
        // Trouver ou créer le service GitHub
        $service = Service::where('name', 'GitHub')->first();
        
        if (!$service) {
            return response()->json(['error' => 'GitHub service not found'], 500);
        }
        
        // Sauvegarder le token pour l'utilisateur
        $userService = UserService::updateOrCreate(
            [
                'user_id' => $userId,
                'service_id' => $service->id
            ],
            [
                'config' => [
                    'access_token' => $accessToken,
                    'github_id' => $githubUser['id'] ?? null,
                    'github_login' => $githubUser['login'] ?? null,
                    'github_name' => $githubUser['name'] ?? null,
                    'scopes' => $data['scope'] ?? '',
                    'token_type' => $data['token_type'] ?? 'bearer'
                ],
                'is_connected' => true,
                'last_used_at' => now()
            ]
        );
        
        // Nettoyer la session
        session()->forget(['github_oauth_state', 'github_oauth_user_id']);
        
        return response()->json([
            'success' => true,
            'message' => 'GitHub connected successfully',
            'data' => [
                'user' => $githubUser['login'] ?? 'Unknown',
                'scopes' => $data['scope'] ?? ''
            ]
        ]);
    }
    
    /**
     * Tester la connexion GitHub
     */
    public function test(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);
        
        $userService = UserService::where('user_id', $request->user_id)
            ->whereHas('service', function($q) {
                $q->where('name', 'GitHub');
            })
            ->first();
        
        if (!$userService || !isset($userService->config['access_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'GitHub not connected'
            ]);
        }
        
        $accessToken = $userService->config['access_token'];
        
        // Tester l'API
        $response = Http::withToken($accessToken)
            ->get('https://api.github.com/user');
        
        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'message' => 'GitHub connection working',
                'data' => $response->json()
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'GitHub token invalid',
                'error' => $response->body()
            ]);
        }
    }
    
    /**
     * Déconnecter GitHub
     */
    public function disconnect(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);
        
        $deleted = UserService::where('user_id', $request->user_id)
            ->whereHas('service', function($q) {
                $q->where('name', 'GitHub');
            })
            ->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'GitHub disconnected',
            'deleted' => $deleted
        ]);
    }
}