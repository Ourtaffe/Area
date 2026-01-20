<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserService;
use App\Models\Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ConnectGitHub extends Command
{
    protected $signature = 'github:connect {user_id}';
    protected $description = 'Connect user to GitHub with Personal Access Token';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User {$userId} not found");
            $this->line("Available users:");
            User::all()->each(function($u) {
                $this->line("  ID: {$u->id}, Email: {$u->email}");
            });
            return 1;
        }
        
        $this->info(" User: {$user->name} ({$user->email})");
        
        // Instructions
        $this->line("\n INSTRUCTIONS:");
        $this->line("1. Go to: https://github.com/settings/tokens");
        $this->line("2. Click 'Generate new token (classic)'");
        $this->line("3. Token name: AREA-Test");
        $this->line("4. Expiration: 90 days");
        $this->line("5. Select scopes: repo (ALL), read:user");
        $this->line("6. Generate token and copy it (starts with ghp_)");
        $this->line("");
        
        // Demander le token
        $token = $this->secret('Paste your GitHub token:');
        
        if (empty($token) || !str_starts_with($token, 'ghp_')) {
            $this->error('Invalid token. Must start with ghp_');
            return 1;
        }
        
        // Tester le token
        $this->info(' Testing token...');
        $response = Http::withToken($token)
            ->withHeaders([
                'User-Agent' => 'AREA-App',
                'Accept' => 'application/vnd.github.v3+json'
            ])
            ->get('https://api.github.com/user');
        
        if (!$response->successful()) {
            $this->error(' Invalid token. Status: ' . $response->status());
            $this->line('Response: ' . $response->body());
            return 1;
        }
        
        $githubUser = $response->json();
        $this->info(" Token valid! Connected as: @{$githubUser['login']}");
        
        // Trouver le service GitHub
        $service = Service::where('name', 'GitHub')->first();
        
        if (!$service) {
            // Créer le service s'il n'existe pas
            $service = Service::create([
                'name' => 'GitHub',
                'auth_type' => 'oauth',
                'description' => 'GitHub repository events'
            ]);
            $this->info(" Created GitHub service (ID: {$service->id})");
        }
        
        // Sauvegarder la connexion
        $userService = UserService::updateOrCreate(
            [
                'user_id' => $user->id,
                'service_id' => $service->id
            ],
            [
                'config' => [
                    'access_token' => $token,
                    'github_id' => $githubUser['id'],
                    'github_login' => $githubUser['login'],
                    'github_name' => $githubUser['name'] ?? $githubUser['login'],
                    'avatar_url' => $githubUser['avatar_url'],
                    'token_type' => 'pat'
                ],
                'is_connected' => true,
                'last_used_at' => now()
            ]
        );
        
        $this->info(" GitHub connected successfully!");
        $this->table(
            ['Field', 'Value'],
            [
                ['GitHub Account', $githubUser['login']],
                ['Public Repos', $githubUser['public_repos'] ?? 'N/A'],
                ['Followers', $githubUser['followers'] ?? 'N/A'],
                ['Connection ID', $userService->id]
            ]
        );
        
        // Tester immédiatement
        $this->testConnection($token, $user->id);
        
        return 0;
    }
    
    private function testConnection(string $token, int $userId)
    {
        $this->info("\n Testing GitHub API...");
        
        // Test 1: User info
        $response = Http::withToken($token)
            ->withHeaders(['User-Agent' => 'AREA-App'])
            ->get('https://api.github.com/user');
        
        $this->line("1. User API: " . ($response->successful() ? 'YES' : 'NO'));
        
        // Test 2: Repository access
        $response = Http::withToken($token)
            ->withHeaders(['User-Agent' => 'AREA-App'])
            ->get('https://api.github.com/repos/torvalds/linux');
        
        $this->line("2. Repo access: " . ($response->successful() ? 'yes' : 'NO'));
        
        // Test 3: Stargazers (ce qu'on utilise pour l'AREA)
        $response = Http::withToken($token)
            ->withHeaders(['User-Agent' => 'AREA-App'])
            ->get('https://api.github.com/repos/torvalds/linux/stargazers', [
                'per_page' => 1
            ]);
        
        if ($response->successful()) {
            $this->line("3. Stargazers API:  (" . count($response->json()) . " stars)");
            
            // Tester le service
            $this->info("\n Testing GitHubService...");
            
            // Modifier temporairement le service pour utiliser ce token
            $service = new \App\Services\GitHubService();
            
            // Utiliser la réflexion pour tester
            try {
                $reflection = new \ReflectionClass($service);
                $method = $reflection->getMethod('checkNewStars');
                $method->setAccessible(true);
                
                $result = $method->invoke($service, 'torvalds/linux', $token, now()->subDays(30));
                
                if ($result === false) {
                    $this->line("   ⏭ No new stars in last 30 days (normal)");
                    
                    // Tester avec un repo plus actif
                    $result2 = $method->invoke($service, 'microsoft/vscode', $token, now()->subDays(7));
                    if ($result2 !== false) {
                        $this->info("    Service working! Found stars on vscode");
                    } else {
                        $this->line("   ⏭ No stars on vscode either");
                    }
                } else {
                    $this->info("    Service working! Found: " . ($result['message'] ?? 'stars'));
                }
            } catch (\Exception $e) {
                $this->error("    Test failed: " . $e->getMessage());
            }
        } else {
            $this->line("3. Stargazers API:  (scope missing?)");
        }
        
        $this->info("\n Ready to test AREA #21!");
        $this->line("Run: php artisan area:check-hooks");
    }
}
