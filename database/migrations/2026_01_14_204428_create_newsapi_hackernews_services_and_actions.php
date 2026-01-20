<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $newsapiId = null;
        $hackernewsId = null;
        
        // NewsAPI
        if (!DB::table('services')->where('name', 'NewsAPI')->exists()) {
            $newsapiId = DB::table('services')->insertGetId([
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
        } else {
            $newsapiId = DB::table('services')->where('name', 'NewsAPI')->value('id');
        }
        
        // HackerNews
        if (!DB::table('services')->where('name', 'HackerNews')->exists()) {
            $hackernewsId = DB::table('services')->insertGetId([
                'name' => 'HackerNews',
                'auth_type' => 'none',
                'description' => 'Get tech news from Hacker News',
                'config_schema' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            $hackernewsId = DB::table('services')->where('name', 'HackerNews')->value('id');
        }
        
        // 2. Créer les actions
        if ($newsapiId) {
            $this->createNewsAPIActions($newsapiId);
        }
        
        if ($hackernewsId) {
            $this->createHackerNewsActions($hackernewsId);
        }
    }
    
    private function createNewsAPIActions($serviceId)
    {
        $actions = [
            [
                'service_id' => $serviceId,
                'name' => 'New articles with keyword',
                'identifier' => 'newsapi_new_articles',
                'description' => 'Trigger when new articles containing a keyword are published',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'service_id' => $serviceId,
                'name' => 'Articles about specific topic',
                'identifier' => 'newsapi_articles_topic',
                'description' => 'Trigger when articles about a specific topic are published',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];
        
        foreach ($actions as $action) {
            if (!DB::table('actions')->where('identifier', $action['identifier'])->exists()) {
                DB::table('actions')->insert($action);
            }
        }
    }
    
    private function createHackerNewsActions($serviceId)
    {
        $actions = [
            [
                'service_id' => $serviceId,
                'name' => 'Top X posts',
                'identifier' => 'hackernews_top_posts',
                'description' => 'Trigger when a post enters the top X',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'service_id' => $serviceId,
                'name' => 'Posts with keyword',
                'identifier' => 'hackernews_posts_keyword',
                'description' => 'Trigger when posts containing a keyword are published',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];
        
        foreach ($actions as $action) {
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