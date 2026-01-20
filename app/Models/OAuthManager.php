<?php

namespace App\Auth;

use App\Models\OAuthProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuthManager
{
    /**
     * Get a valid access token for a provider
     */
    public static function getAccessToken(int $userId, string $provider): ?string
    {
        $oauth = OAuthProvider::where('user_id', $userId)
            ->where('provider', $provider)
            ->first();

        if (!$oauth) {
            Log::warning("AuthManager: No OAuth for $provider (user $userId)");
            return null;
        }

        // Token still valid ✅
        if ($oauth->expires_at && $oauth->expires_at->isFuture()) {
            return $oauth->access_token;
        }

        // Try refresh 🔄
        if ($oauth->refresh_token) {
            return self::refreshToken($oauth);
        }

        Log::error("AuthManager: Token expired and no refresh token ($provider)");
        return null;
    }

    /**
     * Refresh OAuth token
     */
    protected static function refreshToken(OAuthProvider $oauth): ?string
    {
        try {
            $provider = $oauth->provider;

            return match ($provider) {
                'spotify' => self::refreshSpotify($oauth),
                'google'  => self::refreshGoogle($oauth),
                default   => null,
            };
        } catch (\Exception $e) {
            Log::error("AuthManager: Refresh failed ($provider)", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Spotify refresh token
     */
    protected static function refreshSpotify(OAuthProvider $oauth): ?string
    {
        $response = Http::asForm()->post(
            'https://accounts.spotify.com/api/token',
            [
                'grant_type' => 'refresh_token',
                'refresh_token' => $oauth->refresh_token,
                'client_id' => env('SPOTIFY_CLIENT_ID'),
                'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
            ]
        );

        if (!$response->successful()) {
            Log::error('Spotify refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();

        $oauth->update([
            'access_token' => $data['access_token'],
            'expires_at' => now()->addSeconds($data['expires_in']),
            'refresh_token' => $data['refresh_token'] ?? $oauth->refresh_token,
        ]);

        return $data['access_token'];
    }

    /**
     * Google refresh token (Gmail, YouTube)
     */
    protected static function refreshGoogle(OAuthProvider $oauth): ?string
    {
        $response = Http::asForm()->post(
            'https://oauth2.googleapis.com/token',
            [
                'client_id' => env('GOOGLE_CLIENT_ID'),
                'client_secret' => env('GOOGLE_CLIENT_SECRET'),
                'refresh_token' => $oauth->refresh_token,
                'grant_type' => 'refresh_token',
            ]
        );

        if (!$response->successful()) {
            Log::error('Google refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();

        $oauth->update([
            'access_token' => $data['access_token'],
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return $data['access_token'];
    }
}
