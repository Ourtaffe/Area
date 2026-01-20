<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\UserService;

class GitHubService implements ServiceInterface
{
    /**
     * Vérifier les actions GitHub
     */
    public function checkAction(string $actionName, array $params, ?Carbon $lastExecutedAt, ?int $userId = null): array|false
    {
        try {
            Log::info('GitHubService: Checking action', [
                'action' => $actionName,
                'params' => $params,
                'userId' => $userId
            ]);

            // POUR LE DÉVELOPPEMENT: Simulation si pas de token ou pour tests
            if (true) { // Force simulation for testing {
                Log::info('GitHubService: Using simulation mode for development');
                return $this->simulateGitHubData($actionName, $params);
            }
            
            // CODE PRODUCTION - Récupérer le token OAuth
            $accessToken = $this->getUserAccessToken($userId);
            
            if (empty($accessToken)) {
                Log::warning('GitHubService: No access token available for user ' . $userId);
                return false;
            }

            // Support des différents formats d'identifiants
            switch ($actionName) {
                case 'github_new_star':
                case 'github_new_stars':
                case 'new_star':
                    $repo = $this->extractRepoName($params);
                    if (empty($repo)) {
                        Log::warning('GitHubService: Missing repository parameter for stars');
                        return false;
                    }
                    return $this->checkNewStars($repo, $accessToken, $lastExecutedAt);
                    
                case 'github_new_issue':
                case 'new_issue':
                    $repo = $this->extractRepoName($params);
                    if (empty($repo)) {
                        Log::warning('GitHubService: Missing repository parameter for issues');
                        return false;
                    }
                    return $this->checkNewIssues($repo, $accessToken, $lastExecutedAt);
                    
                case 'github_pr_merged':
                case 'pr_merged':
                    $repo = $this->extractRepoName($params);
                    if (empty($repo)) {
                        Log::warning('GitHubService: Missing repository parameter for PRs');
                        return false;
                    }
                    return $this->checkMergedPRs($repo, $accessToken, $lastExecutedAt, $params);
                    
                case 'github_new_follower':
                case 'new_follower':
                    return $this->checkNewFollowers($accessToken, $lastExecutedAt);
                    
                case 'github_new_repository':
                case 'new_repo':
                    return $this->checkNewRepositories($accessToken, $lastExecutedAt);
                    
                default:
                    Log::warning('GitHubService: Unknown action ' . $actionName);
                    return false;
            }
        } catch (\Exception $e) {
            Log::error('GitHubService Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Vérifier si l'utilisateur a un token valide
     */
    private function hasValidToken(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }
        
        $userService = UserService::where('user_id', $userId)
            ->whereHas('service', function ($query) {
                $query->where('name', 'GitHub');
            })
            ->first();

        return $userService && !empty($userService->config['access_token']);
    }

    /**
     * Récupérer le token d'accès de l'utilisateur
     */
    private function getUserAccessToken(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }
        
        $userService = UserService::where('user_id', $userId)
            ->whereHas('service', function ($query) {
                $query->where('name', 'GitHub');
            })
            ->first();

        return $userService->config['access_token'] ?? null;
    }

    /**
     * Extraire le nom du repository des paramètres
     */
    private function extractRepoName(array $params): string
    {
        // Support multiple parameter names
        return $params['repo'] ?? $params['repo_name'] ?? $params['repository'] ?? '';
    }

    /**
     * Simulation de données GitHub pour les tests
     */
    private function simulateGitHubData(string $actionName, array $params): array
    {
        $repo = $this->extractRepoName($params) ?: 'microsoft/vscode';
        $now = now();
        
        Log::info('GitHubService: Simulating data for action', ['action' => $actionName, 'repo' => $repo]);
        
        switch ($actionName) {
            case 'github_new_star':
            case 'github_new_stars':
            case 'new_star':
                return [
                    'triggered' => true,
                    'data' => [
                        'count' => 3,
                        'stars' => [
                            [
                                'user' => 'github_fan',
                                'avatar' => 'https://github.com/github_fan.png',
                                'starred_at' => $now->subMinutes(45)->toISOString(),
                                'login' => 'github_fan'
                            ],
                            [
                                'user' => 'opensource_lover',
                                'avatar' => 'https://github.com/opensource_lover.png',
                                'starred_at' => $now->subMinutes(30)->toISOString(),
                                'login' => 'opensource_lover'
                            ],
                            [
                                'user' => 'code_enthusiast',
                                'avatar' => 'https://github.com/code_enthusiast.png',
                                'starred_at' => $now->subMinutes(15)->toISOString(),
                                'login' => 'code_enthusiast'
                            ]
                        ],
                        'repo' => $repo,
                        'repo_name' => $repo,
                        'message' => "⭐ **3 nouvelles étoiles sur {$repo}**",
                        'last_star_user' => 'code_enthusiast',
                        'last_star_time' => $now->subMinutes(15)->toISOString(),
                        'starred_at' => $now->subMinutes(15)->toISOString(),
                        'hours_ago' => '0.25',
                        'trigger_reason' => 'Nouvelles étoiles détectées'
                    ]
                ];
                
            case 'github_new_issue':
            case 'new_issue':
                $issueNumber = rand(100, 999);
                return [
                    'triggered' => true,
                    'data' => [
                        'count' => 2,
                        'issues' => [[
                            'title' => 'Bug: Feature not working properly',
                            'number' => $issueNumber,
                            'url' => "https://github.com/{$repo}/issues/{$issueNumber}",
                            'user' => 'bug_reporter',
                            'login' => 'bug_reporter',
                            'created_at' => $now->subHours(3)->toISOString(),
                            'state' => 'open',
                            'body' => 'The new feature is not working as expected when...'
                        ]],
                        'repo' => $repo,
                        'repo_name' => $repo,
                        'message' => "📝 **Nouvelle issue #{$issueNumber} sur {$repo}**",
                        'issue_title' => 'Bug: Feature not working properly',
                        'issue_number' => $issueNumber,
                        'trigger_reason' => 'Nouvelle issue ouverte'
                    ]
                ];
                
            case 'github_pr_merged':
            case 'pr_merged':
                $prNumber = rand(50, 150);
                $additions = rand(100, 500);
                $deletions = rand(50, 200);
                $changedFiles = rand(5, 15);
                
                return [
                    'triggered' => true,
                    'data' => [
                        'count' => 1,
                        'prs' => [[
                            'title' => 'feat: Add new authentication system',
                            'number' => $prNumber,
                            'url' => "https://github.com/{$repo}/pull/{$prNumber}",
                            'user' => 'feature_dev',
                            'login' => 'feature_dev',
                            'merged_at' => $now->subHours(2)->toISOString(),
                            'merged_at_human' => '2 hours ago',
                            'merged_by' => 'maintainer',
                            'base' => 'main',
                            'head' => 'feature/auth',
                            'base_branch' => 'main',
                            'branch' => 'feature/auth',
                            'additions' => $additions,
                            'deletions' => $deletions,
                            'changed_files' => $changedFiles,
                            'state' => 'merged'
                        ]],
                        'repo' => $repo,
                        'repo_name' => $repo,
                        'pr_count' => 1,
                        'latest_pr' => [
                            'number' => $prNumber,
                            'title' => 'feat: Add new authentication system',
                            'user' => ['login' => 'feature_dev'],
                            'merged_by' => 'maintainer',
                            'merged_at_human' => '2 hours ago',
                            'additions' => $additions,
                            'deletions' => $deletions,
                            'changed_files' => $changedFiles,
                            'branch' => 'feature/auth',
                            'base_branch' => 'main',
                            'url' => "https://github.com/{$repo}/pull/{$prNumber}"
                        ],
                        'message' => $this->generatePRMergedMessage([[
                            'number' => $prNumber,
                            'title' => 'feat: Add new authentication system',
                            'user' => ['login' => 'feature_dev'],
                            'merged_by' => 'maintainer',
                            'merged_at_human' => '2 hours ago',
                            'additions' => $additions,
                            'deletions' => $deletions,
                            'changed_files' => $changedFiles,
                            'branch' => 'feature/auth',
                            'base_branch' => 'main',
                            'url' => "https://github.com/{$repo}/pull/{$prNumber}"
                        ]], $repo),
                        'trigger_reason' => 'Nouveau PR mergé détecté'
                    ]
                ];
                
            default:
                Log::warning('GitHubService: Unknown action in simulation: ' . $actionName);
                return false;
        }
    }

    /**
     * Vérifier les nouvelles étoiles
     */
    private function checkNewStars(string $repoName, string $accessToken, ?Carbon $lastCheck): array|false
    {
        Log::info('GitHubService: Checking new stars for repo', ['repo' => $repoName]);
        
        $url = "https://api.github.com/repos/{$repoName}/stargazers";
        
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Accept' => 'application/vnd.github.v3.star+json',
                'User-Agent' => 'AREA-App'
            ])
            ->timeout(15)
            ->get($url, [
                'per_page' => 20,
                'sort' => 'created',
                'direction' => 'desc'
            ]);

        if (!$response->successful()) {
            Log::error('GitHub API error (stars): ' . $response->status() . ' - ' . $response->body());
            return false;
        }

        $stars = $response->json();
        $newStars = [];

        foreach ($stars as $star) {
            if (!isset($star['starred_at'])) {
                continue;
            }
            
            $starredAt = Carbon::parse($star['starred_at']);
            
            // Si c'est la première vérification ou si l'étoile est plus récente
            if (!$lastCheck || $starredAt > $lastCheck) {
                $newStars[] = [
                    'user' => $star['user']['login'] ?? 'Unknown',
                    'login' => $star['user']['login'] ?? 'Unknown',
                    'avatar' => $star['user']['avatar_url'] ?? '',
                    'starred_at' => $starredAt->toISOString(),
                    'hours_ago' => $starredAt->diffInHours(now())
                ];
            }
        }

        if (empty($newStars)) {
            Log::info('GitHubService: No new stars found for repo', ['repo' => $repoName]);
            return false;
        }

        Log::info('GitHubService: Found new stars', ['count' => count($newStars), 'repo' => $repoName]);
        
        return [
            'triggered' => true,
            'data' => [
                'count' => count($newStars),
                'stars' => $newStars,
                'repo' => $repoName,
                'repo_name' => $repoName,
                'message' => "⭐ **" . count($newStars) . " nouvelle(s) étoile(s) sur {$repoName}**",
                'last_star_user' => $newStars[0]['user'] ?? 'Unknown',
                'last_star_time' => $newStars[0]['starred_at'] ?? now()->toISOString(),
                'starred_at' => $newStars[0]['starred_at'] ?? now()->toISOString(),
                'hours_ago' => $newStars[0]['hours_ago'] ?? 0,
                'trigger_reason' => 'Nouvelles étoiles détectées'
            ]
        ];
    }

    /**
     * Vérifier les nouvelles issues
     */
    private function checkNewIssues(string $repoName, string $accessToken, ?Carbon $lastCheck): array|false
    {
        Log::info('GitHubService: Checking new issues for repo', ['repo' => $repoName]);
        
        $url = "https://api.github.com/repos/{$repoName}/issues";
        
        $params = [
            'state' => 'open',
            'sort' => 'created',
            'direction' => 'desc',
            'per_page' => 10
        ];
        
        if ($lastCheck) {
            $params['since'] = $lastCheck->toISOString();
        }

        $response = Http::withToken($accessToken)
            ->withHeaders(['User-Agent' => 'AREA-App'])
            ->timeout(15)
            ->get($url, $params);

        if (!$response->successful()) {
            Log::error('GitHub API error (issues): ' . $response->status() . ' - ' . $response->body());
            return false;
        }

        $issues = $response->json();
        
        // Filtrer les PRs (les issues ont pull_request field null)
        $realIssues = array_filter($issues, function ($issue) {
            return !isset($issue['pull_request']);
        });
        
        if (empty($realIssues)) {
            Log::info('GitHubService: No new issues found for repo', ['repo' => $repoName]);
            return false;
        }

        $formattedIssues = array_map(function ($issue) {
            return [
                'title' => $issue['title'] ?? 'No title',
                'number' => $issue['number'] ?? 0,
                'url' => $issue['html_url'] ?? '',
                'user' => $issue['user']['login'] ?? 'Unknown',
                'login' => $issue['user']['login'] ?? 'Unknown',
                'created_at' => $issue['created_at'] ?? now()->toISOString(),
                'state' => $issue['state'] ?? 'open',
                'body' => substr($issue['body'] ?? '', 0, 200) . '...'
            ];
        }, $realIssues);

        Log::info('GitHubService: Found new issues', ['count' => count($formattedIssues), 'repo' => $repoName]);
        
        return [
            'triggered' => true,
            'data' => [
                'count' => count($formattedIssues),
                'issues' => $formattedIssues,
                'repo' => $repoName,
                'repo_name' => $repoName,
                'message' => "📝 **" . count($formattedIssues) . " nouvelle(s) issue(s) sur {$repoName}**",
                'issue_title' => $formattedIssues[0]['title'] ?? 'No title',
                'issue_number' => $formattedIssues[0]['number'] ?? 0,
                'trigger_reason' => 'Nouvelles issues ouvertes'
            ]
        ];
    }

    /**
     * Vérifier les PRs mergés - VERSION AMÉLIORÉE
     */
    private function checkMergedPRs(string $repoName, string $accessToken, ?Carbon $lastCheck, array $params = []): array|false
    {
        Log::info('GitHubService: Checking merged PRs for repo', ['repo' => $repoName]);
        
        $url = "https://api.github.com/repos/{$repoName}/pulls";
        
        $requestParams = [
            'state' => 'closed',
            'sort' => 'updated',
            'direction' => 'desc',
            'per_page' => 10
        ];

        $response = Http::withToken($accessToken)
            ->withHeaders(['User-Agent' => 'AREA-App'])
            ->timeout(15)
            ->get($url, $requestParams);

        if (!$response->successful()) {
            Log::error('GitHub API error (PRs): ' . $response->status() . ' - ' . $response->body());
            return false;
        }

        $pulls = $response->json();
        $mergedPRs = [];

        $hoursThreshold = $params['hours_threshold'] ?? 24;
        
        foreach ($pulls as $pr) {
            if ($pr['merged_at'] && $pr['state'] === 'closed') {
                $mergedAt = Carbon::parse($pr['merged_at']);
                
                // Vérifier si c'est nouveau (après le dernier check)
                if ($lastCheck && $mergedAt->lte($lastCheck)) {
                    continue;
                }
                
                // Vérifier le seuil temporel
                if ($mergedAt->diffInHours(now()) > $hoursThreshold) {
                    continue; // Trop ancien
                }

                $mergedPRs[] = [
                    'title' => $pr['title'] ?? 'No title',
                    'number' => $pr['number'] ?? 0,
                    'url' => $pr['html_url'] ?? '',
                    'user' => $pr['user']['login'] ?? 'Unknown',
                    'login' => $pr['user']['login'] ?? 'Unknown',
                    'merged_at' => $mergedAt->toISOString(),
                    'merged_at_human' => $mergedAt->diffForHumans(),
                    'merged_by' => $pr['merged_by']['login'] ?? 'Unknown',
                    'base' => $pr['base']['ref'] ?? 'main',
                    'head' => $pr['head']['ref'] ?? 'main',
                    'base_branch' => $pr['base']['ref'] ?? 'main',
                    'branch' => $pr['head']['ref'] ?? 'main',
                    'additions' => $pr['additions'] ?? 0,
                    'deletions' => $pr['deletions'] ?? 0,
                    'changed_files' => $pr['changed_files'] ?? 0,
                    'state' => 'merged'
                ];
            }
        }

        if (empty($mergedPRs)) {
            Log::info('GitHubService: No new merged PRs found for repo', ['repo' => $repoName]);
            return false;
        }

        Log::info('GitHubService: Found new merged PRs', ['count' => count($mergedPRs), 'repo' => $repoName]);
        
        // Préparer les données pour latest_pr
        $latestPR = $mergedPRs[0];
        $latestPRData = [
            'number' => $latestPR['number'],
            'title' => $latestPR['title'],
            'user' => ['login' => $latestPR['user']],
            'merged_by' => $latestPR['merged_by'],
            'merged_at_human' => $latestPR['merged_at_human'],
            'additions' => $latestPR['additions'],
            'deletions' => $latestPR['deletions'],
            'changed_files' => $latestPR['changed_files'],
            'branch' => $latestPR['branch'],
            'base_branch' => $latestPR['base_branch'],
            'url' => $latestPR['url']
        ];
        
        return [
            'triggered' => true,
            'data' => [
                'count' => count($mergedPRs),
                'prs' => $mergedPRs,
                'pr_count' => count($mergedPRs),
                'latest_pr' => $latestPRData,
                'repo' => $repoName,
                'repo_name' => $repoName,
                'message' => $this->generatePRMergedMessage([$latestPRData], $repoName),
                'trigger_reason' => 'Nouveau PR mergé détecté'
            ]
        ];
    }

    /**
     * Générer un message pour les PR mergés
     */
    private function generatePRMergedMessage(array $prs, string $repo): string
    {
        if (empty($prs)) {
            return "Aucun nouveau PR mergé";
        }

        $pr = $prs[0]; // Prendre le plus récent
        
        $message = "✅ **PR #{$pr['number']} mergé sur {$repo}**\n";
        $message .= "📝 **Titre:** {$pr['title']}\n";
        $message .= "👤 **Par:** {$pr['user']['login']}\n";
        $message .= "🔄 **Mergé par:** {$pr['merged_by']}\n";
        $message .= "⏰ **Il y a:** {$pr['merged_at_human']}\n";
        
        if ($pr['additions'] > 0 || $pr['deletions'] > 0) {
            $message .= "📊 **Modifications:** +{$pr['additions']}/-{$pr['deletions']} ({$pr['changed_files']} fichiers)\n";
        }
        
        $message .= "🌿 **De:** {$pr['branch']} → **Vers:** {$pr['base_branch']}\n";
        $message .= "🔗 **Lien:** {$pr['url']}";
        
        if (count($prs) > 1) {
            $message .= "\n📈 **Total:** " . count($prs) . " nouveaux PRs mergés";
        }
        
        return $message;
    }

    /**
     * Vérifier les nouveaux followers
     */
    private function checkNewFollowers(string $accessToken, ?Carbon $lastCheck): array|false
    {
        // À implémenter si besoin
        return false;
    }

    /**
     * Vérifier les nouveaux repositories
     */
    private function checkNewRepositories(string $accessToken, ?Carbon $lastCheck): array|false
    {
        // À implémenter si besoin
        return false;
    }

    /**
     * Exécuter une réaction (GitHub ne fait que des actions)
     */
    public function executeReaction(string $reactionName, array $params, array $actionData = []): array
    {
        Log::info('GitHubService: Attempted to execute reaction', ['reaction' => $reactionName]);
        
        return [
            'success' => false,
            'message' => 'GitHubService ne supporte pas les réactions, seulement les actions'
        ];
    }

    /**
     * Tester la connexion à l'API GitHub
     */
    public function testConnection(): bool
    {
        Log::info('GitHubService: Testing API connection');
        
        try {
            // Test avec une requête publique
            $response = Http::withHeaders(['User-Agent' => 'AREA-App'])
                ->timeout(10)
                ->get('https://api.github.com/zen');
            
            if ($response->successful()) {
                Log::info('GitHubService: API connection successful - ' . $response->body());
                return true;
            }
            
            Log::error('GitHubService: API connection failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
        } catch (\Exception $e) {
            Log::error('GitHubService: API connection exception: ' . $e->getMessage());
        }
        
        return false;
    }
}