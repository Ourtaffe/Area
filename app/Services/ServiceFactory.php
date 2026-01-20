<?php

namespace App\Services;

use InvalidArgumentException;

class ServiceFactory
{
    protected static array $serviceMap = [
        'Timer' => TimerService::class,
        'GitHub' => GitHubService::class,
        'Discord' => DiscordService::class,
        'Email' => EmailService::class,
        'Gmail' => GmailService::class,
        'YouTube' => YouTubeService::class,
        'Twitch' => TwitchService::class,
        'Spotify' => SpotifyService::class,
        'NewsAPI' => NewsAPIService::class,
        'HackerNews' => HackerNewsService::class,
        'Strava' => StravaService::class,
        'LinkedIn' => LinkedInService::class,
        'Telegram' => TelegramService::class,
        'Slack' => SlackService::class,
        'Webhook' => WebhookService::class,
        'Weather' => WeatherService::class,
    ];

    public static function create(string $serviceName): ServiceInterface
    {
        $className = self::$serviceMap[$serviceName] ?? null;

        if ($className === null) {
            throw new InvalidArgumentException("Unknown service: {$serviceName}");
        }

        if (!class_exists($className)) {
            throw new InvalidArgumentException("Service class not found: {$className}");
        }

        return new $className();
    }

    public static function exists(string $serviceName): bool
    {
        return isset(self::$serviceMap[$serviceName]);
    }

    public static function all(): array
    {
        return array_keys(self::$serviceMap);
    }
    
    /**
     * Get service by action identifier
     */
    public static function createFromActionIdentifier(string $identifier): ?ServiceInterface
    {
        // Extraire le nom du service depuis l'identifier
        $parts = explode('_', $identifier);
        $serviceName = $parts[0];
        
        // Capitaliser (newsapi -> NewsAPI)
        $serviceName = ucfirst(strtolower($serviceName));
        
        if ($serviceName === 'Newsapi') {
            $serviceName = 'NewsAPI';
        } elseif ($serviceName === 'Hackernews') {
            $serviceName = 'HackerNews';
        }
        
        return self::exists($serviceName) ? self::create($serviceName) : null;
    }
}