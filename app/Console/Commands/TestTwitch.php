<?php

namespace App\Console\Commands;

use App\Services\ServiceFactory;
use Illuminate\Console\Command;

class TestTwitch extends Command
{
    protected $signature = 'twitch:test 
                           {streamer : Twitch streamer name}
                           {--simulate : Use simulation mode}';
    
    protected $description = 'Test Twitch service connection and stream checking';

    public function handle()
    {
        $streamer = $this->argument('streamer');
        $simulate = $this->option('simulate');
        
        $this->info("🧪 Testing Twitch Service");
        $this->line("Streamer: {$streamer}");
        $this->line("Mode: " . ($simulate ? 'Simulation' : 'Real API'));
        
        try {
            // Créer le service
            $service = ServiceFactory::create('Twitch');
            
            if (!$service) {
                $this->error('TwitchService not found in ServiceFactory');
                $this->line('Make sure TwitchService.php exists and is in ServiceFactory::$serviceMap');
                return 1;
            }
            
            $this->info("✅ TwitchService loaded successfully");
            
            // Tester la connexion
            $this->info("\n🔌 Testing API connection...");
            if ($service->testConnection()) {
                $this->info('✅ API connection successful');
            } else {
                $this->warn('⚠️ API connection failed - using simulation');
                $simulate = true;
            }
            
            // Tester l'action
            $this->info("\n📡 Checking if {$streamer} is live...");
            
            if ($simulate) {
                // Utiliser la simulation
                $reflection = new \ReflectionClass($service);
                if ($reflection->hasMethod('simulateStreamOnline')) {
                    $method = $reflection->getMethod('simulateStreamOnline');
                    $method->setAccessible(true);
                    $result = $method->invoke($service, $streamer);
                } else {
                    $this->error('simulateStreamOnline method not found');
                    return 1;
                }
            } else {
                // Utiliser l'API réelle
                $result = $service->checkAction(
                    'twitch_stream_online',
                    ['streamer_name' => $streamer],
                    null,
                    1
                );
            }
            
            if ($result === false) {
                $this->info("⏭️ {$streamer} is not currently live");
                $this->line("(This is normal if they're offline or simulation returns false)");
            } else {
                $this->info("✅ {$streamer} is LIVE!");
                
                $data = $result['data'] ?? $result;
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Streamer', $data['streamer_name'] ?? 'N/A'],
                        ['Title', $data['stream_title'] ?? 'No title'],
                        ['Game', $data['game_name'] ?? 'Just Chatting'],
                        ['Viewers', number_format($data['viewer_count'] ?? 0)],
                        ['Started', $data['started_at'] ?? now()->toISOString()],
                        ['URL', $data['url'] ?? 'https://twitch.tv']
                    ]
                );
                
                $this->line("\n💬 Message template:");
                $this->line($data['message'] ?? 'No message generated');
                
                $this->line("\n🎯 Ready for AREA creation!");
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->line("Trace: " . $e->getTraceAsString());
            return 1;
        }
    }
}