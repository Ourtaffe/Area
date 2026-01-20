<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\UserService;

class YouTubeService implements ServiceInterface
{
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = env('YOUTUBE_API_KEY');
    }

    public function checkAction(string $actionName, array $params, ?Carbon $lastExecutedAt, ?int $userId = null): array|false
    {
        try {
            Log::info('YouTubeService: Checking action', [
                'action' => $actionName,
                'params' => $params,
                'userId' => $userId
            ]);

            // POUR LE DÉVELOPPEMENT: Simulation si pas de token ou pour tests
            if ((app()->environment('local', 'development') || $userId === null) && !$this->hasValidApiKey($userId)) {
                Log::info('YouTubeService: Using simulation mode for development');
                return $this->simulateYouTubeData($actionName, $params);
            }
            
            // CODE PRODUCTION - Récupérer l'API key
            $apiKey = $this->getUserApiKey($userId) ?? $this->apiKey;
            
            if (empty($apiKey)) {
                Log::warning('YouTubeService: No API key available for user ' . $userId);
                return false;
            }

            switch ($actionName) {
                case 'youtube_new_video':
                case 'new_video':
                    $channelId = $this->extractChannelId($params);
                    if (empty($channelId)) {
                        Log::warning('YouTubeService: Missing channel_id parameter');
                        return false;
                    }
                    return $this->checkNewVideos($channelId, $apiKey, $lastExecutedAt, $params);
                    
                case 'youtube_video_views':
                case 'video_views':
                    $videoId = $params['video_id'] ?? '';
                    $threshold = (int)($params['views_threshold'] ?? 1000);
                    if (empty($videoId)) {
                        Log::warning('YouTubeService: Missing video_id parameter');
                        return false;
                    }
                    return $this->checkVideoViews($videoId, $apiKey, $threshold, $lastExecutedAt, $params);
                    
                default:
                    Log::warning('YouTubeService: Unknown action ' . $actionName);
                    return false;
            }
        } catch (\Exception $e) {
            Log::error('YouTubeService Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Vérifier si l'utilisateur a une clé API valide
     */
    private function hasValidApiKey(?int $userId): bool
    {
        if ($userId) {
            $userService = UserService::where('user_id', $userId)
                ->whereHas('service', function ($query) {
                    $query->where('name', 'YouTube');
                })
                ->first();

            if ($userService && !empty($userService->config['api_key'])) {
                return true;
            }
        }
        
        return !empty($this->apiKey);
    }

    /**
     * Récupérer la clé API de l'utilisateur
     */
    private function getUserApiKey(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }
        
        $userService = UserService::where('user_id', $userId)
            ->whereHas('service', function ($query) {
                $query->where('name', 'YouTube');
            })
            ->first();

        return $userService->config['api_key'] ?? null;
    }

    /**
     * Extraire l'ID de la chaîne
     */
    private function extractChannelId(array $params): string
    {
        return $params['channel_id'] ?? $params['channelId'] ?? '';
    }

    /**
     * Simulation de données YouTube pour les tests
     */
    private function simulateYouTubeData(string $actionName, array $params): array
    {
        Log::info('YouTubeService: Simulating data for action', ['action' => $actionName]);
        
        switch ($actionName) {
            case 'youtube_new_video':
            case 'new_video':
                $channelId = $this->extractChannelId($params) ?: 'UC_x5XG1OV2P6uZZ5FSM9Ttw';
                $now = now();
                $videoCount = rand(1, 3);
                $videos = [];
                
                for ($i = 0; $i < $videoCount; $i++) {
                    $hoursAgo = rand(1, 6);
                    $views = rand(1000, 50000);
                    $likes = (int)($views * rand(5, 15) / 100);
                    
                    $videos[] = [
                        'id' => 'test_video_' . uniqid(),
                        'title' => 'Nouveau tutoriel ' . ['Laravel', 'React', 'Docker', 'Kubernetes'][rand(0, 3)],
                        'published_at' => $now->subHours($hoursAgo)->toISOString(),
                        'published_at_human' => $hoursAgo . ' hours ago',
                        'duration' => rand(5, 45) . 'min',
                        'view_count' => $views,
                        'like_count' => $likes,
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'thumbnail' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg'
                    ];
                }
                
                $latestVideo = $videos[0];
                
                return [
                    'triggered' => true,
                    'data' => [
                        'count' => $videoCount,
                        'videos' => $videos,
                        'latest_video' => [
                            'id' => $latestVideo['id'],
                            'title' => $latestVideo['title'],
                            'published_at_human' => $latestVideo['published_at_human'],
                            'duration' => $latestVideo['duration'],
                            'view_count' => $latestVideo['view_count'],
                            'like_count' => $latestVideo['like_count'],
                            'url' => $latestVideo['url'],
                            'thumbnail' => $latestVideo['thumbnail']
                        ],
                        'channel' => [
                            'id' => $channelId,
                            'name' => 'Google Developers',
                            'avatar' => 'https://yt3.googleusercontent.com/ytc/AIdro_mh5MJKqaLhZJj6qJgIChuD1i3YW4fIFHrzN7tk=s176-c-k-c0x00ffffff-no-rj',
                            'url' => 'https://www.youtube.com/@GoogleDevelopers'
                        ],
                        'message' => $this->generateNewVideoMessage($videos, 'Google Developers'),
                        'trigger_reason' => 'Nouvelles vidéos détectées (simulation)'
                    ]
                ];

            case 'youtube_video_views':
            case 'video_views':
                $videoId = $params['video_id'] ?? 'dQw4w9WgXcQ';
                $threshold = (int)($params['views_threshold'] ?? 1000);
                $viewCount = $threshold + rand(100, 10000);
                $title = 'Tutoriel Laravel Avancé - ' . ['Middleware', 'Events', 'Queues', 'API'][rand(0, 3)];
                $channel = 'Google Developers';
                
                return [
                    'triggered' => true,
                    'data' => [
                        'video_id' => $videoId,
                        'title' => $title,
                        'channel_name' => $channel,
                        'view_count' => $viewCount,
                        'like_count' => (int)($viewCount * rand(5, 15) / 100),
                        'duration' => rand(10, 45) . 'min',
                        'published_at_human' => rand(1, 30) . ' days ago',
                        'url' => 'https://www.youtube.com/watch?v=' . $videoId,
                        'thumbnail' => 'https://img.youtube.com/vi/' . $videoId . '/hqdefault.jpg',
                        'threshold' => $threshold,
                        'threshold_exceeded_by' => $viewCount - $threshold,
                        'message' => $this->generateVideoViewsMessageSimulated($title, $channel, $viewCount, $threshold),
                        'trigger_reason' => 'Vidéo dépasse ' . number_format($threshold) . ' vues (simulation)'
                    ]
                ];

            default:
                Log::warning('YouTubeService: Unknown action in simulation: ' . $actionName);
                return false;
        }
    }

    /**
     * Vérifier les nouvelles vidéos (ITEM 27)
     */
    private function checkNewVideos(string $channelId, string $apiKey, ?Carbon $lastCheck, array $params = []): array|false
    {
        Log::info('YouTubeService: Checking new videos for channel', ['channelId' => $channelId]);

        try {
            // 1. Récupérer les infos de la chaîne
            $channelResponse = Http::timeout(15)
                ->get('https://www.googleapis.com/youtube/v3/channels', [
                    'part' => 'snippet,statistics',
                    'id' => $channelId,
                    'key' => $apiKey
                ]);

            if (!$channelResponse->successful()) {
                Log::error('YouTubeService: Failed to fetch channel info', [
                    'status' => $channelResponse->status(),
                    'body' => $channelResponse->body()
                ]);
                return false;
            }

            $channelData = $channelResponse->json();
            
            if (empty($channelData['items'])) {
                Log::error('YouTubeService: Channel not found', ['channelId' => $channelId]);
                return false;
            }

            $channel = $channelData['items'][0];
            $channelName = $channel['snippet']['title'];
            $channelAvatar = $channel['snippet']['thumbnails']['default']['url'] ?? '';

            // 2. Récupérer les vidéos récentes
            $videosResponse = Http::timeout(15)
                ->get('https://www.googleapis.com/youtube/v3/search', [
                    'part' => 'snippet',
                    'channelId' => $channelId,
                    'order' => 'date',
                    'type' => 'video',
                    'maxResults' => 10,
                    'key' => $apiKey
                ]);

            if (!$videosResponse->successful()) {
                Log::error('YouTubeService: Failed to fetch videos', [
                    'status' => $videosResponse->status(),
                    'body' => $videosResponse->body()
                ]);
                return false;
            }

            $videosData = $videosResponse->json();
            
            if (empty($videosData['items'])) {
                Log::info('YouTubeService: No videos found for channel', ['channelId' => $channelId]);
                return false;
            }

            $newVideos = [];
            $hoursThreshold = $params['hours_threshold'] ?? 48;

            foreach ($videosData['items'] as $video) {
                $publishedAt = Carbon::parse($video['snippet']['publishedAt']);
                
                // Vérifier si c'est nouveau
                if ($lastCheck && $publishedAt->lte($lastCheck)) {
                    continue;
                }

                // Vérifier le seuil temporel
                if ($publishedAt->diffInHours(now()) > $hoursThreshold) {
                    continue;
                }

                // Récupérer les détails de la vidéo
                $videoDetails = $this->getVideoDetails($video['id']['videoId'], $apiKey);
                
                $newVideos[] = [
                    'id' => $video['id']['videoId'],
                    'title' => $video['snippet']['title'],
                    'description' => substr($video['snippet']['description'] ?? '', 0, 200) . '...',
                    'thumbnail' => $video['snippet']['thumbnails']['high']['url'] ?? $video['snippet']['thumbnails']['default']['url'],
                    'published_at' => $publishedAt->toISOString(),
                    'published_at_human' => $publishedAt->diffForHumans(),
                    'url' => 'https://www.youtube.com/watch?v=' . $video['id']['videoId'],
                    'duration' => $videoDetails['duration'] ?? 'N/A',
                    'view_count' => $videoDetails['view_count'] ?? 0,
                    'like_count' => $videoDetails['like_count'] ?? 0,
                    'comment_count' => $videoDetails['comment_count'] ?? 0
                ];
            }

            if (empty($newVideos)) {
                Log::info('YouTubeService: No new videos found for channel', [
                    'channelId' => $channelId,
                    'channelName' => $channelName
                ]);
                return false;
            }

            Log::info('YouTubeService: Found new videos', [
                'count' => count($newVideos),
                'channel' => $channelName
            ]);

            // Préparer les données
            $latestVideo = $newVideos[0];
            
            return [
                'triggered' => true,
                'data' => [
                    'count' => count($newVideos),
                    'videos' => $newVideos,
                    'latest_video' => [
                        'id' => $latestVideo['id'],
                        'title' => $latestVideo['title'],
                        'published_at_human' => $latestVideo['published_at_human'],
                        'duration' => $latestVideo['duration'],
                        'view_count' => $latestVideo['view_count'],
                        'like_count' => $latestVideo['like_count'],
                        'url' => $latestVideo['url'],
                        'thumbnail' => $latestVideo['thumbnail']
                    ],
                    'channel' => [
                        'id' => $channelId,
                        'name' => $channelName,
                        'avatar' => $channelAvatar,
                        'url' => 'https://www.youtube.com/channel/' . $channelId
                    ],
                    'message' => $this->generateNewVideoMessage($newVideos, $channelName),
                    'trigger_reason' => 'Nouvelles vidéos détectées sur ' . $channelName
                ]
            ];

        } catch (\Exception $e) {
            Log::error('YouTubeService checkNewVideos error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifier si une vidéo dépasse un seuil de vues (ITEM 28)
     */
    private function checkVideoViews(string $videoId, string $apiKey, int $threshold, ?Carbon $lastCheck, array $params = []): array|false
    {
        Log::info('YouTubeService: Checking video views', [
            'videoId' => $videoId,
            'threshold' => $threshold
        ]);

        try {
            // Récupérer les détails de la vidéo
            $response = Http::timeout(15)
                ->get('https://www.googleapis.com/youtube/v3/videos', [
                    'part' => 'snippet,statistics,contentDetails',
                    'id' => $videoId,
                    'key' => $apiKey
                ]);

            if (!$response->successful()) {
                Log::error('YouTubeService: Failed to fetch video details', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }

            $videoData = $response->json();
            
            if (empty($videoData['items'])) {
                Log::error('YouTubeService: Video not found', ['videoId' => $videoId]);
                return false;
            }

            $video = $videoData['items'][0];
            $viewCount = (int)($video['statistics']['viewCount'] ?? 0);
            $likeCount = (int)($video['statistics']['likeCount'] ?? 0);
            $commentCount = (int)($video['statistics']['commentCount'] ?? 0);
            
            // Vérifier si le seuil est dépassé
            if ($viewCount < $threshold) {
                Log::info('YouTubeService: View threshold not reached', [
                    'videoId' => $videoId,
                    'views' => $viewCount,
                    'threshold' => $threshold
                ]);
                return false;
            }

            // Vérifier l'intervalle de vérification
            $checkInterval = $params['check_interval'] ?? 6; // heures par défaut
            if ($lastCheck && $lastCheck->diffInHours(now()) < $checkInterval) {
                Log::info('YouTubeService: Check interval not reached', [
                    'videoId' => $videoId,
                    'lastCheck' => $lastCheck,
                    'interval' => $checkInterval
                ]);
                return false;
            }

            $publishedAt = Carbon::parse($video['snippet']['publishedAt']);
            $duration = $this->formatDuration($video['contentDetails']['duration'] ?? 'PT0S');
            
            Log::info('YouTubeService: View threshold reached!', [
                'videoId' => $videoId,
                'views' => $viewCount,
                'threshold' => $threshold
            ]);

            return [
                'triggered' => true,
                'data' => [
                    'video_id' => $videoId,
                    'title' => $video['snippet']['title'],
                    'description' => substr($video['snippet']['description'] ?? '', 0, 200) . '...',
                    'thumbnail' => $video['snippet']['thumbnails']['high']['url'] ?? $video['snippet']['thumbnails']['default']['url'],
                    'channel_id' => $video['snippet']['channelId'],
                    'channel_name' => $video['snippet']['channelTitle'],
                    'published_at' => $publishedAt->toISOString(),
                    'published_at_human' => $publishedAt->diffForHumans(),
                    'duration' => $duration,
                    'view_count' => $viewCount,
                    'like_count' => $likeCount,
                    'comment_count' => $commentCount,
                    'url' => 'https://www.youtube.com/watch?v=' . $videoId,
                    'threshold' => $threshold,
                    'threshold_exceeded_by' => $viewCount - $threshold,
                    'message' => $this->generateVideoViewsMessage($video, $viewCount, $threshold),
                    'trigger_reason' => 'Vidéo dépasse ' . number_format($threshold) . ' vues'
                ]
            ];

        } catch (\Exception $e) {
            Log::error('YouTubeService checkVideoViews error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer les détails d'une vidéo
     */
    private function getVideoDetails(string $videoId, string $apiKey): array
    {
        try {
            $response = Http::timeout(10)
                ->get('https://www.googleapis.com/youtube/v3/videos', [
                    'part' => 'contentDetails,statistics',
                    'id' => $videoId,
                    'key' => $apiKey
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['items'])) {
                    $item = $data['items'][0];
                    
                    $duration = $this->formatDuration($item['contentDetails']['duration'] ?? 'PT0S');
                    
                    return [
                        'duration' => $duration,
                        'view_count' => $item['statistics']['viewCount'] ?? 0,
                        'like_count' => $item['statistics']['likeCount'] ?? 0,
                        'comment_count' => $item['statistics']['commentCount'] ?? 0
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('YouTubeService: Failed to get video details', [
                'videoId' => $videoId,
                'error' => $e->getMessage()
            ]);
        }

        return [];
    }

    /**
     * Formater la durée ISO 8601
     */
    private function formatDuration(string $duration): string
    {
        try {
            $interval = new \DateInterval($duration);
            
            $parts = [];
            if ($interval->h > 0) {
                $parts[] = $interval->h . 'h';
            }
            if ($interval->i > 0) {
                $parts[] = $interval->i . 'min';
            }
            if ($interval->s > 0 && empty($parts)) {
                $parts[] = $interval->s . 's';
            }
            
            return implode(' ', $parts) ?: '0s';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Générer un message pour les nouvelles vidéos
     */
    private function generateNewVideoMessage(array $videos, string $channelName): string
    {
        if (empty($videos)) {
            return "Aucune nouvelle vidéo";
        }

        $video = $videos[0];
        
        $message = "🎬 **Nouvelle vidéo sur {$channelName}**\n";
        $message .= "📺 **Titre:** {$video['title']}\n";
        $message .= "⏰ **Publiée:** {$video['published_at_human']}\n";
        $message .= "⏱️ **Durée:** {$video['duration']}\n";
        
        if ($video['view_count'] > 0) {
            $message .= "👁️ **Vues:** " . number_format($video['view_count']) . "\n";
        }
        
        if ($video['like_count'] > 0) {
            $message .= "👍 **Likes:** " . number_format($video['like_count']) . "\n";
        }
        
        $message .= "🔗 **Lien:** {$video['url']}";
        
        if (count($videos) > 1) {
            $message .= "\n📈 **Total:** " . count($videos) . " nouvelles vidéos";
        }
        
        return $message;
    }

    /**
     * Générer un message pour le seuil de vues dépassé
     */
    private function generateVideoViewsMessage(array $video, int $viewCount, int $threshold): string
    {
        $title = $video['snippet']['title'];
        $channel = $video['snippet']['channelTitle'];
        $exceededBy = $viewCount - $threshold;
        
        $message = "🔥 **Vidéo dépasse " . number_format($threshold) . " vues!**\n";
        $message .= "📺 **Titre:** {$title}\n";
        $message .= "👤 **Chaîne:** {$channel}\n";
        $message .= "👁️ **Vues actuelles:** " . number_format($viewCount) . "\n";
        $message .= "🎯 **Seuil dépassé de:** " . number_format($exceededBy) . " vues\n";
        
        if (isset($video['statistics']['likeCount'])) {
            $message .= "👍 **Likes:** " . number_format($video['statistics']['likeCount']) . "\n";
        }
        
        $message .= "🔗 **Lien:** https://www.youtube.com/watch?v=" . $video['id'];
        
        return $message;
    }

    /**
     * Message simulé pour le seuil de vues
     */
    private function generateVideoViewsMessageSimulated(string $title, string $channel, int $viewCount, int $threshold): string
    {
        $exceededBy = $viewCount - $threshold;
        
        $message = "🔥 **Vidéo dépasse " . number_format($threshold) . " vues!**\n";
        $message .= "📺 **Titre:** {$title}\n";
        $message .= "👤 **Chaîne:** {$channel}\n";
        $message .= "👁️ **Vues actuelles:** " . number_format($viewCount) . "\n";
        $message .= "🎯 **Seuil dépassé de:** " . number_format($exceededBy) . " vues\n";
        $message .= "👍 **Likes:** " . number_format((int)($viewCount * rand(5, 15) / 100)) . "\n";
        $message .= "🔗 **Lien:** https://www.youtube.com/watch?v=dQw4w9WgXcQ";
        
        return $message;
    }

    /**
     * Exécuter une réaction (YouTube ne fait que des actions)
     */
    public function executeReaction(string $reactionName, array $params, array $actionData = []): array
    {
        Log::info('YouTubeService: Attempted to execute reaction', ['reaction' => $reactionName]);
        
        return [
            'success' => false,
            'message' => 'YouTubeService ne supporte pas les réactions, seulement les actions'
        ];
    }

    /**
     * Tester la connexion à l'API YouTube
     */
    public function testConnection(): bool
    {
        if (empty($this->apiKey)) {
            Log::warning('YouTubeService: No API key configured');
            return false;
        }

        try {
            // Test simple de l'API
            $response = Http::timeout(10)
                ->get('https://www.googleapis.com/youtube/v3/videos', [
                    'part' => 'snippet',
                    'id' => 'dQw4w9WgXcQ',
                    'key' => $this->apiKey
                ]);
            
            if ($response->successful()) {
                Log::info('YouTubeService: API connection successful');
                return true;
            }
            
            Log::error('YouTubeService: API connection failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
        } catch (\Exception $e) {
            Log::error('YouTubeService: API connection exception: ' . $e->getMessage());
        }
        
        return false;
    }
}