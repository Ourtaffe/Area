<?php

namespace App\Services;

use App\Models\Action; // <-- IMPORTANT
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class NewsAPIService implements ServiceInterface
{
    protected Client $client;
    protected string $apiKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = config('services.newsapi.key', env('NEWSAPI_KEY'));
    }

    /**
     * Vérifie si une action doit être déclenchée
     */
    public function checkAction(string $actionIdentifier, array $params, ?Carbon $lastExecutedAt, ?int $userId = null): array|false
    {
        // Toutes les actions NewsAPI utilisent la même logique
        return $this->checkArticles($params, $lastExecutedAt);
    }

    /**
     * Vérifie les articles
     */
    private function checkArticles(array $params, ?Carbon $lastExecutedAt): array|false
    {
        $keyword = $params['keyword'] ?? '';
        $language = $params['language'] ?? 'fr';
        
        if (empty($keyword)) {
            Log::warning('NewsAPI: No keyword specified');
            return false;
        }
        
        $articles = $this->fetchArticles($keyword, $language, $lastExecutedAt);
        
        if (empty($articles)) {
            return false;
        }
        
        return [
            'success' => true,
            'data' => [
                'article' => $articles[0],
                'articles_count' => count($articles),
                'keyword' => $keyword,
                'language' => $language,
                'message' => "📰 New article on '{$keyword}': {$articles[0]['title']}"
            ],
            'articles' => $articles
        ];
    }

    /**
     * Récupère les articles depuis l'API
     */
    private function fetchArticles(string $keyword, string $language, ?Carbon $lastExecutedAt = null): array
    {
        $url = 'https://newsapi.org/v2/everything';
        
        $queryParams = [
            'q' => $keyword,
            'apiKey' => $this->apiKey,
            'language' => $language,
            'pageSize' => 5,
            'sortBy' => 'publishedAt'
        ];

        if ($lastExecutedAt) {
            $queryParams['from'] = $lastExecutedAt->format('Y-m-d\TH:i:s');
        } else {
            // Par défaut, dernières 24h
            $queryParams['from'] = now()->subDay()->format('Y-m-d\TH:i:s');
        }

        try {
            $response = $this->client->get($url, ['query' => $queryParams]);
            $data = json_decode($response->getBody(), true);

            if ($data['status'] === 'ok') {
                return $this->formatArticles($data['articles'] ?? [], $keyword, $language);
            }

            Log::error('NewsAPI Error: ' . ($data['message'] ?? 'Unknown error'));
            return [];
        } catch (\Exception $e) {
            Log::error('NewsAPI Request Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Formate les articles
     */
    private function formatArticles(array $articles, string $keyword, string $language): array
    {
        $formatted = [];
        
        foreach ($articles as $article) {
            $formatted[] = [
                'title' => $article['title'] ?? 'No title',
                'description' => $article['description'] ?? '',
                'content' => $article['content'] ?? '',
                'url' => $article['url'] ?? '#',
                'source' => $article['source']['name'] ?? 'Unknown',
                'author' => $article['author'] ?? 'Anonymous',
                'published_at' => $article['publishedAt'] ?? now()->toISOString(),
                'image_url' => $article['urlToImage'] ?? null,
                'keyword' => $keyword,
                'language' => $language,
                
                // Variables pour les templates
                'variables' => [
                    'title' => $article['title'] ?? '',
                    'description' => $article['description'] ?? '',
                    'url' => $article['url'] ?? '',
                    'source' => $article['source']['name'] ?? '',
                    'author' => $article['author'] ?? '',
                    'keyword' => $keyword,
                    'language' => $language,
                    'published_at' => $article['publishedAt'] ?? '',
                ]
            ];
        }
        
        return $formatted;
    }

    /**
     * Exécute une réaction
     */
    public function executeReaction(string $reactionIdentifier, array $params, array $actionData = []): array
    {
        // NewsAPI n'est pas une réaction
        return [
            'success' => false,
            'message' => 'NewsAPI cannot be used as a reaction',
            'data' => []
        ];
    }

    /**
     * Teste la connexion
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->client->get('https://newsapi.org/v2/top-headlines', [
                'query' => [
                    'apiKey' => $this->apiKey,
                    'country' => 'us',
                    'pageSize' => 1
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            return $data['status'] === 'ok';
        } catch (\Exception $e) {
            Log::error('NewsAPI Connection Test Failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les paramètres depuis une action
     */
    public function getParamsFromAction(Action $action): array
    {
        $config = json_decode($action->config, true);
        return $config['params'] ?? [];
    }
}