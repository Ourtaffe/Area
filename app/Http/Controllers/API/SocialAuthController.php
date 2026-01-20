<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserService;
use App\Models\Service;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect to OAuth provider or return demo token
     */
    public function redirect($provider)
    {
        // Demo provider - simulates OAuth without external service
        if ($provider === 'demo') {
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:8083');
            $demoToken = 'demo_' . bin2hex(random_bytes(16));
            return redirect("$frontendUrl/oauth/callback?token=$demoToken&provider=demo");
        }

        // Real OAuth providers
        $config = config("services.$provider");
        if (!$config || empty($config['client_id'])) {
            $providerUpper = strtoupper($provider);
            return response()->json([
                'error' => "Le provider '$provider' n'est pas configuré❌. Ajoutez les variables d'environnement correspondantes.",
                'required' => [
                    "{$providerUpper}_CLIENT_ID",
                    "{$providerUpper}_CLIENT_SECRET", 
                    "{$providerUpper}_REDIRECT_URL"
                ]
            ], 400);
        }

        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * Handle OAuth callback
     */
    public function callback(Request $request, $provider)
    {
        try {
            // Demo provider - create user with demo token
            if ($provider === 'demo') {
                return $this->handleDemoCallback($request);
            }

            // Real OAuth callback
            $socialUser = Socialite::driver($provider)->stateless()->user();
            
            $user = User::firstOrCreate(
                ['email' => $socialUser->getEmail()],
                [
                    'name' => $socialUser->getName(),
                    'password' => bcrypt(str()->random(16)),
                ]
            );

            // Store the OAuth connection in user_services
            $service = Service::where('name', ucfirst($provider))->first();
            if ($service) {
                UserService::updateOrCreate(
                    ['user_id' => $user->id, 'service_id' => $service->id],
                    ['config' => [
                        'provider' => $provider,
                        'access_token' => $socialUser->token,
                        'refresh_token' => $socialUser->refreshToken ?? null,
                    ]]
                );
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            $frontendUrl = env('FRONTEND_URL', 'http://localhost:8083');
            return redirect("$frontendUrl/oauth/callback?token=$token");

        } catch (\Exception $e) {
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:8083');
            return redirect("$frontendUrl/oauth/callback?error=" . urlencode($e->getMessage()));
        }
    }

    /**
     * Handle demo OAuth callback - creates a demo user
     */
    private function handleDemoCallback(Request $request)
    {
        $user = User::firstOrCreate(
            ['email' => 'demo_oauth@example.com'],
            [
                'name' => 'Demo OAuth User',
                'password' => bcrypt('demo123'),
            ]
        );

        $token = $user->createToken('auth_token')->plainTextToken;

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:8083');
        return redirect("$frontendUrl/oauth/callback?token=$token");
    }

    /**
     * Connect a service for the authenticated user
     */
    public function connectService(Request $request, $provider)
    {
        $user = $request->user();
        $providerLower = strtolower($provider);
        
        // Try to find service with exact match or ucfirst
        $service = Service::where('name', $provider)
            ->orWhere('name', ucfirst($provider))
            ->first();
            
        if (!$service) {
            return response()->json(['error' => "Service '$provider' non trouvé"], 404);
        }

        // Services that can be connected directly (NO OAuth required)
        // These are services that only use API keys or don't need authentication
        $directConnectServices = ['demo', 'timer', 'webhook', 'weather', 'newsapi', 'hackernews', 'earthquake', 'youtube', 'gmail'];
        
        if (in_array($providerLower, $directConnectServices)) {
            UserService::updateOrCreate(
                ['user_id' => $user->id, 'service_id' => $service->id],
                ['config' => ['connected' => true, 'connected_at' => now()]]
            );

            return response()->json([
                'response_code' => 200,
                'status' => 'success',
                'message' => "Service {$service->name} connecté avec succès"
            ]);
        }

        // OAuth services - require real OAuth flow
        // These are: google, discord, github, spotify, twitch
        return response()->json([
            'response_code' => 200,
            'status' => 'success',
            'redirect_url' => url("/api/auth/$providerLower")
        ]);
    }
}
