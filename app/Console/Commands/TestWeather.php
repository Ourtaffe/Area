<?php

namespace App\Console\Commands;

use App\Services\ServiceFactory;
use Illuminate\Console\Command;

class TestWeather extends Command
{
    protected $signature = 'weather:test 
                           {city : City name}
                           {country=FR : Country code}
                           {--action= : Action to test}
                           {--threshold= : Temperature threshold}
                           {--condition= : Weather condition}';
    
    protected $description = 'Test Weather service with OpenWeather API';

    public function handle()
    {
        $city = $this->argument('city');
        $country = $this->argument('country');
        
        $this->info("🌤️ Testing Weather Service");
        $this->line("📍 Location: {$city}, {$country}");
        
        $service = ServiceFactory::create('Weather');
        
        if (!$service) {
            $this->error('❌ WeatherService not found in ServiceFactory');
            return 1;
        }
        
        // Tester la connexion
        $this->info("\n🔌 Testing API connection...");
        if ($service->testConnection()) {
            $this->info('✅ API connection successful');
        } else {
            $this->error('❌ API connection failed');
            $this->line('Check:');
            $this->line('1. OPENWEATHER_API_KEY in .env');
            $this->line('2. Internet connection');
            $this->line('3. API key validity');
            return 1;
        }
        
        // Tester une action
        $action = $this->option('action') ?? 'weather_daily_report';
        $params = [
            'city' => $city,
            'country' => $country
        ];
        
        if ($this->option('threshold')) {
            $params['threshold'] = (float)$this->option('threshold');
        }
        
        if ($this->option('condition')) {
            $params['condition'] = $this->option('condition');
        }
        
        $this->info("\n📡 Checking weather for action: {$action}");
        
        $result = $service->checkAction($action, $params, null, 1);
        
        if ($result === false) {
            $this->info("⏭️ Condition not met for action: {$action}");
            
            // Afficher quand même les données météo
            $this->info("\n🌡️ Current weather data:");
            $this->testSimpleWeather($city, $country);
            
        } else {
            $this->info("✅ Condition MET! Triggered: {$result['data']['trigger_reason']}");
            
            $data = $result['data'];
            
            $this->table(
                ['Field', 'Value'],
                [
                    ['🌍 Location', "{$data['city']}, {$data['country']}"],
                    ['🌡️ Temperature', "{$data['temperature']}°C (feels {$data['feels_like']}°C)"],
                    ['☁️ Conditions', "{$data['weather_emoji']} {$data['weather_description']}"],
                    ['💧 Humidity', "{$data['humidity']}%"],
                    ['💨 Wind', "{$data['wind_speed']} m/s"],
                    ['🌅 Sunrise/Sunset', "{$data['sunrise']} / {$data['sunset']}"],
                    ['🕐 Updated', $data['timestamp']]
                ]
            );
            
            $this->line("\n💬 Generated message:");
            $this->line($data['message']);
            
            $this->line("\n🎯 Ready for AREA creation!");
        }
        
        return 0;
    }
    
    private function testSimpleWeather(string $city, string $country)
    {
        try {
            $apiKey = env('OPENWEATHER_API_KEY');
            $response = \Illuminate\Support\Facades\Http::get('https://api.openweathermap.org/data/2.5/weather', [
                'q' => "{$city},{$country}",
                'appid' => $apiKey,
                'units' => 'metric',
                'lang' => 'fr'
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Temperature', round($data['main']['temp'], 1) . '°C'],
                        ['Feels like', round($data['main']['feels_like'], 1) . '°C'],
                        ['Conditions', $data['weather'][0]['description']],
                        ['Humidity', $data['main']['humidity'] . '%'],
                        ['Wind', $data['wind']['speed'] . ' m/s']
                    ]
                );
            }
        } catch (\Exception $e) {
            $this->error('Simple test failed: ' . $e->getMessage());
        }
    }
}
