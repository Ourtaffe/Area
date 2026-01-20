<?php

namespace App\Console\Commands;

use App\Services\ServiceFactory;
use Illuminate\Console\Command;
use Carbon\Carbon;

class TestHackerNews extends Command
{
    protected $signature = 'hackernews:test 
                            {--keyword= : Search by keyword}
                            {--top= : Get top X posts}';
    
    protected $description = 'Test Hacker News service';

    public function handle()
    {
        $keyword = $this->option('keyword');
        $top = $this->option('top');
        
        $this->info(" Testing Hacker News Service");
        
        if ($keyword) {
            $this->line("Mode: Keyword search");
            $this->line("Keyword: <comment>{$keyword}</comment>");
        } elseif ($top) {
            $this->line("Mode: Top posts");
            $this->line("Top: <comment>{$top}</comment> posts");
        } else {
            $this->error("Please specify --keyword or --top");
            return 1;
        }
        
        try {
            $service = ServiceFactory::create('HackerNews');
            
            // Test connection
            $this->info("\n🔌 Testing API connection...");
            if ($service->testConnection()) {
                $this->info(' <fg=green>Connection successful</>');
            } else {
                $this->error(' Connection failed');
                return 1;
            }
            
            // Prepare params based on mode
            $actionName = $keyword ? 'posts_with_keyword' : 'top_posts';
            $params = $keyword ? ['keyword' => $keyword] : ['top' => (int)$top];
            
            // Test action
            $this->info("\n Testing action: {$actionName}");
            
            $lastExecutedAt = Carbon::now()->subDay();
            $result = $service->checkAction($actionName, $params, $lastExecutedAt);
            
            if ($result === false) {
                $this->warn("\n No new posts found");
            } else {
                $this->info("\n Action successful!");
                
                $data = $result['data'] ?? [];
                $posts = $result['posts'] ?? [$data['post'] ?? []];
                
                $this->line("Found " . count($posts) . " post(s):");
                
                foreach ($posts as $index => $post) {
                    $num = $index + 1;
                    $this->line("\n<fg=cyan>#{$num}</> <fg=white>{$post['title']}</>");
                    $this->line("    Author: <comment>{$post['author']}</comment>");
                    $this->line("    Points: <comment>{$post['points']}</comment>");
                    $this->line("   Comments: <comment>{$post['comments']}</comment>");
                    $this->line("    Created: <comment>{$post['created_at']}</comment>");
                    $this->line("    URL: <fg=blue>{$post['url']}</>");
                    
                    if (isset($post['position'])) {
                        $this->line("    Position: <fg=yellow>#{$post['position']}</>");
                    }
                }
                
                // Show example message
                if (!empty($posts)) {
                    $this->info("\n Example message:");
                    $example = $posts[0];
                    $message = $example['message'] ?? "Hacker News: {$example['title']}";
                    $this->line($message);
                }
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("\n Error: " . $e->getMessage());
            return 1;
        }
    }
}