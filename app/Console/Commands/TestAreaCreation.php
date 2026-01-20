<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\Action;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Console\Command;

class TestAreaCreation extends Command
{
    protected $signature = 'area:test-create';
    protected $description = 'Test creating sample AREAs';

    public function handle()
    {
        // Trouver un utilisateur
        $user = User::first();
        if (!$user) {
            $this->error('No user found');
            return 1;
        }
        
        $this->info("Creating AREAs for user: {$user->email}");
        
        // Trouver les actions et réactions
        $newsapiAction = Action::where('identifier', 'newsapi_new_articles')->first();
        $hackernewsAction = Action::where('identifier', 'hackernews_top_posts')->first();
        $discordReaction = Reaction::where('identifier', 'like', '%discord%')->first();
        
        if (!$newsapiAction || !$hackernewsAction || !$discordReaction) {
            $this->error('Required actions/reactions not found. Run migrations first.');
            return 1;
        }
        
        // Créer AREA #33: NewsAPI → Discord
        $area1 = Area::create([
            'user_id' => $user->id,
            'name' => 'Tech News Notifier',
            'action_id' => $newsapiAction->id,
            'reaction_id' => $discordReaction->id,
            'action_params' => [
                'keyword' => 'technology',
                'language' => 'en'
            ],
            'reaction_params' => [
                'channel_id' => 'test_channel_123',
                'message' => ' {{title}}\n {{url}}'
            ],
            'is_active' => true
        ]);
        
        $this->info(" Created AREA #33: {$area1->name}");
        
        // Créer AREA #35: HackerNews → Discord
        $area2 = Area::create([
            'user_id' => $user->id,
            'name' => 'HackerNews Top 10',
            'action_id' => $hackernewsAction->id,
            'reaction_id' => $discordReaction->id,
            'action_params' => [
                'top' => 10
            ],
            'reaction_params' => [
                'channel_id' => 'test_channel_123',
                'message' => ' HackerNews Top 10\n {{title}}\n {{points}} points'
            ],
            'is_active' => true
        ]);
        
        $this->info(" Created AREA #35: {$area2->name}");
        
        // Tester les AREAs
        $this->info("\n Testing AREAs...");
        $this->call('area:check-hooks');
        
        return 0;
    }
}