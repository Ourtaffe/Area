<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DebugNewsAPI extends Command
{
    protected $signature = 'newsapi:raw {keyword} {--lang=en}';
    protected $description = 'Make raw NewsAPI request';

    public function handle()
    {
        $keyword = $this->argument('keyword');
        $language = $this->option('lang');
        $apiKey = env('NEWSAPI_KEY');
        
        if (!$apiKey) {
            $this->error('NEWSAPI_KEY not set in .env');
            return 1;
        }
        
        $this->info("🔧 Raw NewsAPI Debug");
        $this->line("Keyword: {$keyword}");
        $this->line("Language: {$language}");
        $this->line("API Key: " . substr($apiKey, 0, 8) . '...');
        
        $url = 'https://newsapi.org/v2/everything?' . http_build_query([
            'q' => $keyword,
            'apiKey' => $apiKey,
            'language' => $language,
            'pageSize' => 3,
            'from' => date('Y-m-d', strtotime('-7 days')),
            'sortBy' => 'publishedAt'
        ]);
        
        $this->line("\n🌐 Request URL (shortened):");
        $this->line(str_replace($apiKey, 'API_KEY_HIDDEN', $url));
        
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->get($url);
            
            $this->info("\n✅ HTTP Status: " . $response->getStatusCode());
            
            $data = json_decode($response->getBody(), true);
            
            $this->line("\n Response Summary:");
            $this->line("Status: " . ($data['status'] ?? 'unknown'));
            $this->line("Total Results: " . ($data['totalResults'] ?? 0));
            $this->line("Articles: " . count($data['articles'] ?? []));
            
            if (isset($data['message'])) {
                $this->warn("API Message: " . $data['message']);
            }
            
            if (!empty($data['articles'])) {
                $this->info("\n Articles found:");
                foreach ($data['articles'] as $i => $article) {
                    $this->line(($i+1) . ". " . ($article['title'] ?? 'No title'));
                    $this->line("   Source: " . ($article['source']['name'] ?? 'Unknown'));
                    $this->line("   Published: " . ($article['publishedAt'] ?? 'No date'));
                    $this->line("   URL: " . ($article['url'] ?? 'No URL'));
                }
            } else {
                $this->warn("\n No articles found");
                
                // Test with different parameters
                $this->info("\n🔍 Testing alternative parameters...");
                
                $testUrls = [
                    'Top headlines (US)' => 'https://newsapi.org/v2/top-headlines?apiKey=' . $apiKey . '&country=us&pageSize=1',
                    'Everything no language filter' => 'https://newsapi.org/v2/everything?q=technology&apiKey=' . $apiKey . '&pageSize=1',
                    'French headlines' => 'https://newsapi.org/v2/top-headlines?apiKey=' . $apiKey . '&country=fr&pageSize=1',
                ];
                
                foreach ($testUrls as $testName => $testUrl) {
                    $this->line("\n" . $testName . ":");
                    try {
                        $testResponse = $client->get($testUrl);
                        $testData = json_decode($testResponse->getBody(), true);
                        $this->line("   Status: " . ($testData['status'] ?? 'error'));
                        $this->line("   Results: " . ($testData['totalResults'] ?? 0));
                    } catch (\Exception $e) {
                        $this->error("   Error: " . $e->getMessage());
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->error("\ Request failed: " . $e->getMessage());
            
            // Check specific Guzzle errors
            if (str_contains($e->getMessage(), '401')) {
                $this->error('API Key is invalid or expired');
            } elseif (str_contains($e->getMessage(), '429')) {
                $this->error('Rate limit exceeded');
            }
        }
        
        return 0;
    }
}