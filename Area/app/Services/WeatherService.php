<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService implements ServiceInterface
{
    private $apiKey;
    
    public function __construct()
    {
        $this->apiKey = env('WEATHERAPI_KEY');
        
        if (empty($this->apiKey)) {
            Log::warning('WeatherService: Using free demo mode');
        }
    }
    
    public function checkAction(string $actionName, array $params, ?Carbon $lastExecutedAt, ?int $userId = null): array|false
    {
        $city = $params['city'] ?? 'Paris';
        
        // Obtenir la météo
        $weatherData = $this->getCurrentWeather($city);
        
        if (!$weatherData) {
            return false;
        }
        
        $weatherInfo = [
            'temperature' => $weatherData['current']['temp_c'],
            'feels_like' => $weatherData['current']['feelslike_c'],
            'humidity' => $weatherData['current']['humidity'],
            'weather' => $weatherData['current']['condition']['text'],
            'weather_icon' => 'https:' . $weatherData['current']['condition']['icon'],
            'wind_speed' => $weatherData['current']['wind_kph'],
            'city' => $weatherData['location']['name'],
            'country' => $weatherData['location']['country'],
            'timestamp' => $weatherData['current']['last_updated']
        ];
        
        switch ($actionName) {
            case 'weather_temperature_above':
                $threshold = (float)($params['threshold'] ?? 20);
                if ($weatherInfo['temperature'] > $threshold) {
                    return $this->createTrigger($weatherInfo, "🌡️ > {$threshold}°C");
                }
                break;
                
            case 'weather_daily_report':
                return $this->createTrigger($weatherInfo, "📊 Rapport quotidien");
                
            default:
                return $this->createTrigger($weatherInfo, "🌤️ Météo actuelle");
        }
        
        return false;
    }
    
    private function getCurrentWeather(string $city): ?array
    {
        try {
            $apiKey = $this->apiKey ?: 'b6eeb146d8a149a5a1f130345242602'; // Clé demo
            
            $response = Http::timeout(10)
                ->get('https://api.weatherapi.com/v1/current.json', [
                    'key' => $apiKey,
                    'q' => $city,
                    'lang' => 'fr'
                ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('WeatherAPI error: ' . $response->body());
            
        } catch (\Exception $e) {
            Log::error('WeatherAPI exception: ' . $e->getMessage());
        }
        
        return null;
    }
    
    private function createTrigger(array $weatherInfo, string $reason): array
    {
        return [
            'triggered' => true,
            'data' => array_merge($weatherInfo, [
                'temp_emoji' => $this->getTemperatureEmoji($weatherInfo['temperature']),
                'weather_emoji' => $this->getWeatherEmoji($weatherInfo['weather']),
                'trigger_reason' => $reason,
                'message' => $this->generateWeatherMessage($weatherInfo)
            ])
        ];
    }
    
    private function generateWeatherMessage(array $data): string
    {
        return "🌤️ **Météo à {$data['city']}**\n" .
               "🌡️ Température: **{$data['temperature']}°C**\n" .
               "☁️ Conditions: {$data['weather']}\n" .
               "💧 Humidité: {$data['humidity']}%\n" .
               "💨 Vent: {$data['wind_speed']} km/h";
    }
    
    private function getTemperatureEmoji(float $temp): string
    {
        if ($temp > 30) return '🔥';
        if ($temp > 25) return '☀️';
        if ($temp > 20) return '😎';
        if ($temp > 15) return '🌤️';
        if ($temp > 10) return '⛅';
        if ($temp > 5) return '🌥️';
        if ($temp > 0) return '❄️';
        return '🥶';
    }
    
    private function getWeatherEmoji(string $condition): string
    {
        $condition = strtolower($condition);
        
        if (str_contains($condition, 'sun') || str_contains($condition, 'clear')) return '☀️';
        if (str_contains($condition, 'cloud')) return '☁️';
        if (str_contains($condition, 'rain')) return '🌧️';
        if (str_contains($condition, 'storm')) return '⛈️';
        if (str_contains($condition, 'snow')) return '❄️';
        if (str_contains($condition, 'fog') || str_contains($condition, 'mist')) return '🌫️';
        
        return '🌈';
    }
    
    public function executeReaction(string $reactionName, array $params, array $actionData = []): array
    {
        return [
            'success' => false,
            'message' => 'WeatherService ne supporte pas les réactions'
        ];
    }
    
    public function testConnection(): bool
    {
        echo "🧪 Testing WeatherAPI...\n";
        
        try {
            $apiKey = $this->apiKey ?: 'b6eeb146d8a149a5a1f130345242602'; // Clé demo gratuite
            
            $response = Http::timeout(5)
                ->get('https://api.weatherapi.com/v1/current.json', [
                    'key' => $apiKey,
                    'q' => 'Paris',
                    'lang' => 'fr'
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                echo "✅ API fonctionne!\n";
                echo "📍 {$data['location']['name']}, {$data['location']['country']}\n";
                echo "🌡️ {$data['current']['temp_c']}°C\n";
                echo "☁️ {$data['current']['condition']['text']}\n";
                return true;
            }
            
            echo "❌ Erreur: " . $response->status() . "\n";
            echo "Message: " . $response->body() . "\n";
            
        } catch (\Exception $e) {
            echo "❌ Exception: " . $e->getMessage() . "\n";
        }
        
        return false;
    }
}