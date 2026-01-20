<?php

namespace App\Services;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class HackerNewsService implements ServiceInterface
{
    protected Client $client;
    protected string $apiUrl = 'https://hn.algolia.com/api/v1/';

    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * Vérifie les actions Hacker News
     */
    public function checkAction(string $actionName, array $params, ?Carbon $lastExecutedAt, ?int $userId = null): array|false
    {
        switch ($actionName) {
            case 'top_posts':
                return $this->checkTopPosts($params, $lastExecutedAt);
            
            case 'posts_with_keyword':
                return $this->checkPostsWithKeyword($params, $lastExecutedAt);
            
            default:
                Log::warning("HackerNews: Unknown action: {$actionName}");
                return false;
        }
    }

    /**
     * Vérifie les posts dans le top X (AREA #35)
     */
    private function checkTopPosts(array $params, ?Carbon $lastExecutedAt): array|false
    {
        $topX = $params['top'] ?? 10;
        $posts = $this->fetchTopPosts($topX);
        
        if (empty($posts)) {
            return false;
        }
        
        // Filtrer les nouveaux posts depuis lastExecutedAt
        $newPosts = [];
        foreach ($posts as $post) {
            $postTime = Carbon::createFromTimestamp($post['created_at_i'] ?? time());
            
            if (!$lastExecutedAt || $postTime->greaterThan($lastExecutedAt)) {
                $newPosts[] = $post;
            }
        }
        
        if (empty($newPosts)) {
            return false;
        }
        
        return [
            'success' => true,
            'data' => [
                'post' => $newPosts[0],
                'posts_count' => count($newPosts),
                'top' => $topX,
                'message' => "📰 Hacker News Top {$topX}: {$newPosts[0]['title']}"
            ],
            'posts' => $newPosts
        ];
    }

    /**
     * Vérifie les posts avec mot-clé (AREA #36)
     */
    private function checkPostsWithKeyword(array $params, ?Carbon $lastExecutedAt): array|false
    {
        $keyword = $params['keyword'] ?? '';
        
        if (empty($keyword)) {
            Log::warning('HackerNews: No keyword specified');
            return false;
        }
        
        $posts = $this->fetchPostsByKeyword($keyword);
        
        if (empty($posts)) {
            return false;
        }
        
        // Filtrer les nouveaux posts
        $newPosts = [];
        foreach ($posts as $post) {
            $postTime = Carbon::createFromTimestamp($post['created_at_i'] ?? time());
            
            if (!$lastExecutedAt || $postTime->greaterThan($lastExecutedAt)) {
                $newPosts[] = $post;
            }
        }
        
        if (empty($newPosts)) {
            return false;
        }
        
        return [
            'success' => true,
            'data' => [
                'post' => $newPosts[0],
                'posts_count' => count($newPosts),
                'keyword' => $keyword,
                'message' => "🔍 Hacker News '{$keyword}': {$newPosts[0]['title']}"
            ],
            'posts' => $newPosts
        ];
    }

    /**
     * Récupère les posts du top X
     */
    private function fetchTopPosts(int $limit = 10): array
    {
        $url = $this->apiUrl . 'search';
        
        try {
            $response = $this->client->get($url, [
                'query' => [
                    'tags' => 'front_page',
                    'hitsPerPage' => $limit
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            return $this->formatHnPosts($data['hits'] ?? []);
            
        } catch (\Exception $e) {
            Log::error('HackerNews fetchTopPosts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les posts par mot-clé
     */
    private function fetchPostsByKeyword(string $keyword): array
    {
        $url = $this->apiUrl . 'search';
        
        try {
            $response = $this->client->get($url, [
                'query' => [
                    'query' => $keyword,
                    'tags' => 'story',
                    'hitsPerPage' => 10
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            return $this->formatHnPosts($data['hits'] ?? []);
            
        } catch (\Exception $e) {
            Log::error('HackerNews fetchPostsByKeyword error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Formate les posts Hacker News
     */
    private function formatHnPosts(array $hits): array
    {
        $formatted = [];
        
        foreach ($hits as $hit) {
            $formatted[] = [
                'id' => $hit['objectID'] ?? '',
                'title' => $hit['title'] ?? 'No title',
                'url' => $hit['url'] ?? 'https://news.ycombinator.com/item?id=' . ($hit['objectID'] ?? ''),
                'points' => $hit['points'] ?? 0,
                'comments' => $hit['num_comments'] ?? 0,
                'author' => $hit['author'] ?? 'Anonymous',
                'created_at_i' => $hit['created_at_i'] ?? time(),
                'created_at' => date('Y-m-d H:i:s', $hit['created_at_i'] ?? time()),
                
                'message' => "📰 {$hit['title']} ({$hit['points']} points)",
                'short_message' => substr($hit['title'] ?? '', 0, 50) . '...',
            ];
        }
        
        return $formatted;
    }

    /**
     * Exécute une réaction (HackerNews n'est pas une réaction)
     */
    public function executeReaction(string $reactionName, array $params, array $actionData = []): array
    {
        return [
            'success' => false,
            'message' => 'HackerNews cannot be used as a reaction service',
            'data' => []
        ];
    }

    /**
     * Teste la connexion
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->client->get($this->apiUrl . 'search', [
                'query' => ['tags' => 'front_page', 'hitsPerPage' => 1]
            ]);
            
            $data = json_decode($response->getBody(), true);
            return isset($data['hits']);
        } catch (\Exception $e) {
            Log::error('HackerNews connection test failed: ' . $e->getMessage());
            return false;
        }
    }
}