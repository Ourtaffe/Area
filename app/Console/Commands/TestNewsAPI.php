<?php

namespace App\Console\Commands;

use App\Services\ServiceFactory;
use Illuminate\Console\Command;
use Carbon\Carbon;

class TestNewsAPI extends Command
{
    protected $signature = 'newsapi:test 
                            {keyword : Keyword to search for}
                            {--lang=fr : Language (fr, en, es, etc.)}
                            {--action=new_articles : Action name (new_articles, articles_with_keyword)}
                            {--days=7 : Look back X days}'; 

    protected $description = 'Test NewsAPI service connection and article fetching';

    public function handle()
    {
        $keyword = $this->argument('keyword');
        $language = $this->option('lang');
        $actionName = $this->option('action');
        $daysBack = $this->option('days');
        
        $this->info(" Testing NewsAPI Service");
        $this->line("Keyword: <comment>{$keyword}</comment>");
        $this->line("Language: <comment>{$language}</comment>");
        $this->line("Action: <comment>{$actionName}</comment>");
        $this->line("Days back: <comment>{$daysBack}</comment>");
        $this->line(str_repeat('─', 50));
        
        try {
            // 1. Créer le service
            $service = ServiceFactory::create('NewsAPI');
            
            // 2. Tester la connexion
            $this->info('🔌 Testing API connection...');
            if ($service->testConnection()) {
                $this->info(' <fg=green>Connection successful</>');
            } else {
                $this->error(' Connection failed - Check your API key');
                return 1;
            }
            
            // 3. Tester l'action
            $this->info("\n Testing action: {$actionName}");
            
            $params = [
                'keyword' => $keyword,
                'language' => $language
            ];
            
            // MODIFIÉ ICI : Utilise $daysBack au lieu de toujours 1 jour
            $lastExecutedAt = $daysBack > 0 ? Carbon::now()->subDays($daysBack) : null;
            
            $result = $service->checkAction($actionName, $params, $lastExecutedAt);
            
            // 4. Afficher les résultats
            if ($result === false) {
                $this->warn("\n No new articles found for the given criteria.");
                $this->line("Try increasing --days parameter");
            } else {
                $this->info("\n Action successful!");
                
                $data = $result['data'] ?? [];
                $articles = $result['articles'] ?? [$data['article'] ?? []];
                
                $this->line("Found " . count($articles) . " article(s):");
                $this->line(str_repeat('─', 80));
                
                foreach ($articles as $index => $article) {
                    $num = $index + 1;
                    $this->line("<fg=cyan>#{$num}</> <fg=white>{$article['title']}</>");
                    $this->line("    " . substr($article['description'] ?? 'No description', 0, 100) . '...');
                    $this->line("    Source: <comment>{$article['source']}</comment>");
                    $this->line("    Published: <comment>{$article['published_at']}</comment>");
                    $this->line("    URL: <fg=blue>{$article['url']}</>");
                    $this->line("    Keyword: <fg=magenta>{$article['keyword']}</>");
                    
                    if ($index < count($articles) - 1) {
                        $this->line(str_repeat('─', 40));
                    }
                }
                
                // 5. Montrer un exemple de message
                $this->info("\n Example message for reactions:");
                $example = $articles[0];
                $message = $example['message'] ?? "New article: {$example['title']}";
                $this->line($message);
            }
            
            $this->line(str_repeat('─', 50));
            $this->info(' Test completed successfully');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("\n Error: " . $e->getMessage());
            $this->line("Trace: " . $e->getTraceAsString());
            return 1;
        }
    }
}