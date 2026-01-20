<?php

namespace App\Console\Commands;

use App\Services\ServiceFactory;
use Illuminate\Console\Command;

class TwitchDebug extends Command
{
    protected $signature = 'twitch:debug 
                           {streamer? : Twitch streamer name}
                           {--test-api : Test API connection only}
                           {--check-live : Check if streamer is live}';
    
    protected $description = 'Debug Twitch API connection and functionality';

    public function handle()
    {
        try {
            $service = ServiceFactory::create('Twitch');
            
            if (!$service) {
                $this->error('❌ TwitchService not found');
                return 1;
            }
            
            $this->info('🧪 Twitch Service Debug');
            $this->line('=====================');
            
            // 1. Afficher les credentials (masqués)
            $debugInfo = $service->debugCredentials();
            
            $this->table(
                ['Setting', 'Value'],
                [
                    ['Client ID', $debugInfo['has_client_id'] ? '✅ Present' : '❌ Missing'],
                    ['Client Secret', $debugInfo['has_client_secret'] ? '✅ Present' : '❌ Missing'],
                    ['Access Token', $debugInfo['has_access_token'] ? '✅ Obtained' : '❌ Failed'],
                ]
            );
            
            // 2. Tester la connexion API
            $this->info("\n🔌 Testing API Connection...");
            if ($service->testConnection()) {
                $this->info('✅ API Connection successful!');
            } else {
                $this->error('❌ API Connection failed');
                $this->line('Check:');
                $this->line('1. TWITCH_CLIENT_ID in .env');
                $this->line('2. TWITCH_CLIENT_SECRET in .env');
                $this->line('3. Internet connection');
                return 1;
            }
            
            // 3. Si streamer spécifié, vérifier le live
            if ($streamer = $this->argument('streamer')) {
                $this->info("\n📡 Checking streamer: {$streamer}");
                
                $result = $service->checkAction(
                    'twitch_stream_online',
                    ['streamer_name' => $streamer],
                    null,
                    1
                );
                
                if ($result === false) {
                    $this->info("⏭️ {$streamer} is not currently live");
                    
                    // Vérifier si le streamer existe
                    $this->info("\n🔍 Verifying streamer exists...");
                    $this->call('twitch:test', ['streamer' => $streamer, '--simulate' => false]);
                } else {
                    $this->info("✅ {$streamer} is LIVE!");
                    
                    $data = $result['data'];
                    $this->table(
                        ['Field', 'Value'],
                        [
                            ['Streamer', $data['streamer_name']],
                            ['Title', $data['stream_title']],
                            ['Game', $data['game_name']],
                            ['Viewers', number_format($data['viewer_count'])],
                            ['Started', $data['started_at_human']],
                            ['URL', $data['url']]
                        ]
                    );
                    
                    $this->info("\n💬 Message généré:");
                    $this->line($data['message']);
                }
            }
            
            $this->info("\n🎯 Twitch API is ready for real use!");
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }
}