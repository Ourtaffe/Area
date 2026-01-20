<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $this->createServices();
        
        $this->createActions();
    }
    
    private function createServices()
    {
        // NewsAPI
        if (!DB::table('services')->where('name', 'NewsAPI')->exists()) {
            DB::table('services')->insert([
                'name' => 'NewsAPI',
                'auth_type' => 'api_key',
                'description' => 'Get news articles from various sources',
                'config_schema' => json_encode([
                    'api_key' => [
                        'type' => 'string',
                        'required' => true,
                        'label' => 'API Key',
                        'placeholder' => 'Your NewsAPI key from newsapi.org'
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // HackerNews
        if (!DB::table('services')->where('name', 'HackerNews')->exists()) {
            DB::table('services')->insert([
                'name' => 'HackerNews',
                'auth_type' => 'none',
                'description' => 'Get tech news from Hacker News',
                'config_schema' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    
    private function createActions()
    {
        $newsapiService = DB::table('services')->where('name', 'NewsAPI')->first();
        $hackernewsService = DB::table('services')->where('name', 'HackerNews')->first();
        
        if (!$newsapiService || !$hackernewsService) {
            return;
        }
        
        // Actions NewsAPI
        $newsapiActions = [
            [
                'service_id' => $newsapiService->id,
                'name' => 'New articles with keyword',
                'identifier' => 'newsapi_new_articles',
                'description' => 'Trigger when new articles containing a keyword are published',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'service_id' => $newsapiService->id,
                'name' => 'Articles about specific topic',
                'identifier' => 'newsapi_articles_topic',
                'description' => 'Trigger when articles about a specific topic are published',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];
        
        // Actions HackerNews
        $hackernewsActions = [
            [
                'service_id' => $hackernewsService->id,
                'name' => 'Top X posts',
                'identifier' => 'hackernews_top_posts',
                'description' => 'Trigger when a post enters the top X',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'service_id' => $hackernewsService->id,
                'name' => 'Posts with keyword',
                'identifier' => 'hackernews_posts_keyword',
                'description' => 'Trigger when posts containing a keyword are published',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];
        
        foreach ($newsapiActions as $action) {
            if (!DB::table('actions')->where('identifier', $action['identifier'])->exists()) {
                DB::table('actions')->insert($action);
            }
        }
        
        foreach ($hackernewsActions as $action) {
            if (!DB::table('actions')->where('identifier', $action['identifier'])->exists()) {
                DB::table('actions')->insert($action);
            }
        }
    }

    public function down()
    {
        DB::table('actions')->where('identifier', 'like', 'newsapi_%')->delete();
        DB::table('actions')->where('identifier', 'like', 'hackernews_%')->delete();
        
        DB::table('services')->where('name', 'NewsAPI')->delete();
        DB::table('services')->where('name', 'HackerNews')->delete();
    }
};