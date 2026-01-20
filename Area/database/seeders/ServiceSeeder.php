<?php

namespace Database\Seeders;

use App\Models\Action;
use App\Models\Reaction;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            // ============== TIMER ==============
            [
                'name' => 'Timer',
                'auth_type' => 'none',
                'description' => 'Timer-based triggers',
                'actions' => [
                    [
                        'name' => 'timer_interval',
                        'identifier' => 'timer_interval',
                        'description' => 'Déclenchement à intervalles réguliers',
                        'parameters_schema' => [
                            'interval_minutes' => [
                                'type' => 'number',
                                'title' => 'Intervalle (minutes)',
                                'description' => 'Intervalle en minutes entre chaque déclenchement',
                                'placeholder' => '60',
                            ],
                        ],
                    ],
                    [
                        'name' => 'every_minute',
                        'identifier' => 'every_minute',
                        'description' => 'Déclenchement toutes les minutes',
                        'parameters_schema' => null,
                    ],
                    [
                        'name' => 'specific_time',
                        'identifier' => 'specific_time',
                        'description' => 'Déclenchement à une heure spécifique',
                        'parameters_schema' => [
                            'hour' => [
                                'type' => 'number',
                                'title' => 'Heure (0-23)',
                                'description' => 'Heure de déclenchement',
                                'placeholder' => '9',
                            ],
                            'minute' => [
                                'type' => 'number',
                                'title' => 'Minute (0-59)',
                                'description' => 'Minute de déclenchement',
                                'placeholder' => '0',
                            ],
                        ],
                    ],
                ],
                'reactions' => [],
            ],

            // ============== WEBHOOK ==============
            [
                'name' => 'Webhook',
                'auth_type' => 'none',
                'description' => 'Generic webhook calls',
                'actions' => [
                    [
                        'name' => 'webhook_receive',
                        'identifier' => 'webhook_receive',
                        'description' => 'Recevoir une requête webhook',
                        'parameters_schema' => null,
                    ],
                ],
                'reactions' => [
                    [
                        'name' => 'call_webhook',
                        'identifier' => 'call_webhook',
                        'description' => 'Appeler une URL webhook',
                        'parameters_schema' => [
                            'url' => [
                                'type' => 'string',
                                'title' => 'URL du webhook',
                                'description' => 'URL à appeler',
                                'placeholder' => 'https://example.com/webhook',
                            ],
                            'method' => [
                                'type' => 'string',
                                'title' => 'Méthode HTTP',
                                'description' => 'GET ou POST',
                                'placeholder' => 'POST',
                            ],
                        ],
                    ],
                ],
            ],

            // ============== WEATHER ==============
            [
                'name' => 'Weather',
                'auth_type' => 'api_key',
                'description' => 'Service météo',
                'actions' => [
                    [
                        'name' => 'weather_temperature_above',
                        'identifier' => 'weather_temperature_above',
                        'description' => 'Température supérieure à un seuil',
                        'parameters_schema' => [
                            'city' => [
                                'type' => 'string',
                                'title' => 'Ville',
                                'description' => 'Nom de la ville à surveiller',
                                'placeholder' => 'Paris',
                            ],
                            'threshold' => [
                                'type' => 'number',
                                'title' => 'Température seuil (°C)',
                                'description' => 'Déclencher si la température dépasse cette valeur',
                                'placeholder' => '30',
                            ],
                        ],
                    ],
                    [
                        'name' => 'weather_temperature_below',
                        'identifier' => 'weather_temperature_below',
                        'description' => 'Température inférieure à un seuil',
                        'parameters_schema' => [
                            'city' => [
                                'type' => 'string',
                                'title' => 'Ville',
                                'description' => 'Nom de la ville à surveiller',
                                'placeholder' => 'Paris',
                            ],
                            'threshold' => [
                                'type' => 'number',
                                'title' => 'Température seuil (°C)',
                                'description' => 'Déclencher si la température est inférieure à cette valeur',
                                'placeholder' => '5',
                            ],
                        ],
                    ],
                    [
                        'name' => 'weather_rain_forecast',
                        'identifier' => 'weather_rain_forecast',
                        'description' => 'Pluie prévue dans les prochaines heures',
                        'parameters_schema' => [
                            'city' => [
                                'type' => 'string',
                                'title' => 'Ville',
                                'description' => 'Nom de la ville à surveiller',
                                'placeholder' => 'Paris',
                            ],
                        ],
                    ],
                ],
                'reactions' => [],
            ],

            // ============== GITHUB ==============
            [
                'name' => 'GitHub',
                'auth_type' => 'oauth2',
                'description' => 'GitHub repository events',
                'actions' => [
                    [
                        'name' => 'new_star',
                        'identifier' => 'new_star',
                        'description' => 'Nouvelle étoile sur un repo',
                        'parameters_schema' => [
                            'repository' => [
                                'type' => 'string',
                                'title' => 'Repository',
                                'description' => 'Nom du repository (format: owner/repo)',
                                'placeholder' => 'facebook/react',
                            ],
                        ],
                    ],
                    [
                        'name' => 'new_issue',
                        'identifier' => 'new_issue',
                        'description' => 'Nouvelle issue ouverte',
                        'parameters_schema' => [
                            'repository' => [
                                'type' => 'string',
                                'title' => 'Repository',
                                'description' => 'Nom du repository (format: owner/repo)',
                                'placeholder' => 'facebook/react',
                            ],
                        ],
                    ],
                    [
                        'name' => 'pr_merged',
                        'identifier' => 'pr_merged',
                        'description' => 'Nouveau PR mergé',
                        'parameters_schema' => [
                            'repository' => [
                                'type' => 'string',
                                'title' => 'Repository',
                                'description' => 'Nom du repository (format: owner/repo)',
                                'placeholder' => 'facebook/react',
                            ],
                        ],
                    ],
                ],
                'reactions' => [],
            ],

            // ============== GMAIL ==============
            [
                'name' => 'Gmail',
                'auth_type' => 'oauth2',
                'description' => 'Google Mail service',
                'actions' => [
                    [
                        'name' => 'new_email',
                        'identifier' => 'new_email',
                        'description' => 'Nouvel email reçu',
                        'parameters_schema' => null,
                    ],
                    [
                        'name' => 'email_with_keyword',
                        'identifier' => 'email_with_keyword',
                        'description' => 'Email contenant un mot-clé',
                        'parameters_schema' => [
                            'keyword' => [
                                'type' => 'string',
                                'title' => 'Mot-clé',
                                'description' => 'Mot-clé à rechercher dans l\'objet ou le contenu',
                                'placeholder' => 'urgent',
                            ],
                        ],
                    ],
                    [
                        'name' => 'email_from_sender',
                        'identifier' => 'email_from_sender',
                        'description' => 'Email de l\'expéditeur X',
                        'parameters_schema' => [
                            'sender_email' => [
                                'type' => 'string',
                                'title' => 'Email de l\'expéditeur',
                                'description' => 'Adresse email de l\'expéditeur à surveiller',
                                'placeholder' => 'boss@company.com',
                            ],
                        ],
                    ],
                ],
                'reactions' => [],
            ],

            // ============== YOUTUBE ==============
            [
                'name' => 'YouTube',
                'auth_type' => 'api_key',
                'description' => 'YouTube video tracking',
                'actions' => [
                    [
                        'name' => 'new_video',
                        'identifier' => 'new_video',
                        'description' => 'Nouvelle vidéo sur une chaîne',
                        'parameters_schema' => [
                            'channel_id' => [
                                'type' => 'string',
                                'title' => 'ID de la chaîne',
                                'description' => 'L\'ID de la chaîne YouTube à surveiller',
                                'placeholder' => 'UCxxxxxxxxxxxxxx',
                            ],
                        ],
                    ],
                    [
                        'name' => 'video_views',
                        'identifier' => 'video_views',
                        'description' => 'Vidéo dépasse X vues',
                        'parameters_schema' => [
                            'video_id' => [
                                'type' => 'string',
                                'title' => 'ID de la vidéo',
                                'description' => 'L\'ID de la vidéo YouTube',
                                'placeholder' => 'dQw4w9WgXcQ',
                            ],
                            'view_threshold' => [
                                'type' => 'number',
                                'title' => 'Seuil de vues',
                                'description' => 'Nombre de vues à dépasser',
                                'placeholder' => '1000000',
                            ],
                        ],
                    ],
                ],
                'reactions' => [],
            ],

            // ============== TWITCH ==============
            [
                'name' => 'Twitch',
                'auth_type' => 'oauth2',
                'description' => 'Twitch streaming service',
                'actions' => [
                    [
                        'name' => 'streamer_live',
                        'identifier' => 'streamer_live',
                        'description' => 'Streamer préféré passe en live',
                        'parameters_schema' => [
                            'streamer_name' => [
                                'type' => 'string',
                                'title' => 'Nom du streamer',
                                'description' => 'Nom d\'utilisateur du streamer Twitch',
                                'placeholder' => 'ninja',
                            ],
                        ],
                    ],
                    [
                        'name' => 'new_follower',
                        'identifier' => 'new_follower',
                        'description' => 'Nouveau follower',
                        'parameters_schema' => null,
                    ],
                ],
                'reactions' => [],
            ],

            // ============== SPOTIFY ==============
            [
                'name' => 'Spotify',
                'auth_type' => 'oauth2',
                'description' => 'Spotify music service',
                'actions' => [
                    [
                        'name' => 'new_song_in_playlist',
                        'identifier' => 'new_song_in_playlist',
                        'description' => 'Nouvelle chanson dans une playlist',
                        'parameters_schema' => [
                            'playlist_id' => [
                                'type' => 'string',
                                'title' => 'ID de la playlist',
                                'description' => 'L\'ID de la playlist Spotify à surveiller',
                                'placeholder' => '37i9dQZF1DXcBWIGoYBM5M',
                            ],
                        ],
                    ],
                    [
                        'name' => 'new_playlist_created',
                        'identifier' => 'new_playlist_created',
                        'description' => 'Nouvelle playlist créée',
                        'parameters_schema' => null,
                    ],
                ],
                'reactions' => [],
            ],

            // ============== NEWSAPI ==============
            [
                'name' => 'NewsAPI',
                'auth_type' => 'api_key',
                'description' => 'News articles API',
                'actions' => [
                    [
                        'name' => 'article_with_keyword',
                        'identifier' => 'article_with_keyword',
                        'description' => 'Article avec mot-clé',
                        'parameters_schema' => [
                            'keyword' => [
                                'type' => 'string',
                                'title' => 'Mot-clé',
                                'description' => 'Mot-clé à rechercher dans les actualités',
                                'placeholder' => 'intelligence artificielle',
                            ],
                        ],
                    ],
                ],
                'reactions' => [],
            ],

            // ============== HACKERNEWS ==============
            [
                'name' => 'HackerNews',
                'auth_type' => 'none',
                'description' => 'Hacker News API',
                'actions' => [
                    [
                        'name' => 'new_top_post',
                        'identifier' => 'new_top_post',
                        'description' => 'Nouveau post dans le top X',
                        'parameters_schema' => [
                            'top_count' => [
                                'type' => 'number',
                                'title' => 'Nombre de places',
                                'description' => 'Surveiller le top X (ex: top 5)',
                                'placeholder' => '5',
                            ],
                        ],
                    ],
                    [
                        'name' => 'post_with_keyword',
                        'identifier' => 'post_with_keyword',
                        'description' => 'Post avec mot-clé',
                        'parameters_schema' => [
                            'keyword' => [
                                'type' => 'string',
                                'title' => 'Mot-clé',
                                'description' => 'Mot-clé à rechercher',
                                'placeholder' => 'Laravel',
                            ],
                        ],
                    ],
                ],
                'reactions' => [],
            ],

            // ============== EARTHQUAKE ==============
            [
                'name' => 'Earthquake',
                'auth_type' => 'none',
                'description' => 'Earthquake detection',
                'actions' => [
                    [
                        'name' => 'earthquake_detected',
                        'identifier' => 'earthquake_detected',
                        'description' => 'Séisme supérieur à X sur l\'échelle de Richter',
                        'parameters_schema' => [
                            'magnitude' => [
                                'type' => 'number',
                                'title' => 'Magnitude minimale',
                                'description' => 'Déclencher pour les séismes de cette magnitude ou plus',
                                'placeholder' => '5.0',
                            ],
                        ],
                    ],
                ],
                'reactions' => [],
            ],

            // ============== RANDOMQUOTE ==============
            [
                'name' => 'RandomQuote',
                'auth_type' => 'none',
                'description' => 'Random quote generator',
                'actions' => [
                    [
                        'name' => 'random_quote_fetch',
                        'identifier' => 'random_quote_fetch',
                        'description' => 'Déclencher manuellement une citation',
                        'parameters_schema' => null,
                    ],
                    [
                        'name' => 'random_quote_daily',
                        'identifier' => 'random_quote_daily',
                        'description' => 'Envoyer une citation par jour',
                        'parameters_schema' => null,
                    ],
                ],
                'reactions' => [],
            ],

            // ============== STRAVA ==============
            [
                'name' => 'Strava',
                'auth_type' => 'oauth2',
                'description' => 'Strava fitness tracking',
                'actions' => [
                    [
                        'name' => 'new_running_activity',
                        'identifier' => 'new_running_activity',
                        'description' => 'Nouvelle activité running',
                        'parameters_schema' => null,
                    ],
                    [
                        'name' => 'personal_record_broken',
                        'identifier' => 'personal_record_broken',
                        'description' => 'Record personnel battu',
                        'parameters_schema' => null,
                    ],
                ],
                'reactions' => [],
            ],

            // ============== LINKEDIN ==============
            [
                'name' => 'LinkedIn',
                'auth_type' => 'oauth2',
                'description' => 'LinkedIn professional network',
                'actions' => [
                    [
                        'name' => 'new_connection',
                        'identifier' => 'new_connection',
                        'description' => 'Nouvelle connexion',
                        'parameters_schema' => null,
                    ],
                    [
                        'name' => 'post_liked',
                        'identifier' => 'post_liked',
                        'description' => 'Nouveau post aimé',
                        'parameters_schema' => null,
                    ],
                ],
                'reactions' => [],
            ],

            // ============== DISCORD (Réaction) ==============
            [
                'name' => 'Discord',
                'auth_type' => 'none',
                'description' => 'Discord webhook integration',
                'actions' => [],
                'reactions' => [
                    [
                        'name' => 'send_message',
                        'identifier' => 'send_message',
                        'description' => 'Envoyer un message via webhook Discord',
                        'parameters_schema' => [
                            'webhook_url' => [
                                'type' => 'string',
                                'title' => 'URL du Webhook',
                                'description' => 'L\'URL du webhook Discord',
                                'placeholder' => 'https://discord.com/api/webhooks/...',
                            ],
                            'message' => [
                                'type' => 'string',
                                'title' => 'Message',
                                'description' => 'Le message à envoyer',
                                'placeholder' => 'Notification: {event}',
                            ],
                        ],
                    ],
                ],
            ],

            // ============== EMAIL (Réaction) ==============
            [
                'name' => 'Email',
                'auth_type' => 'none',
                'description' => 'Send email notifications',
                'actions' => [],
                'reactions' => [
                    [
                        'name' => 'send_email',
                        'identifier' => 'send_email',
                        'description' => 'Envoyer un email',
                        'parameters_schema' => [
                            'to' => [
                                'type' => 'string',
                                'title' => 'Destinataire',
                                'description' => 'Adresse email du destinataire',
                                'placeholder' => 'exemple@gmail.com',
                            ],
                            'subject' => [
                                'type' => 'string',
                                'title' => 'Sujet',
                                'description' => 'Sujet de l\'email',
                                'placeholder' => 'Notification AREA',
                            ],
                        ],
                    ],
                ],
            ],

            // ============== TELEGRAM (Réaction) ==============
            [
                'name' => 'Telegram',
                'auth_type' => 'api_key',
                'description' => 'Telegram bot notifications',
                'actions' => [],
                'reactions' => [
                    [
                        'name' => 'send_telegram_message',
                        'identifier' => 'send_telegram_message',
                        'description' => 'Envoyer un message Telegram',
                        'parameters_schema' => [
                            'chat_id' => [
                                'type' => 'string',
                                'title' => 'Chat ID',
                                'description' => 'ID du chat Telegram',
                                'placeholder' => '123456789',
                            ],
                            'message' => [
                                'type' => 'string',
                                'title' => 'Message',
                                'description' => 'Le message à envoyer',
                                'placeholder' => 'Notification: {event}',
                            ],
                        ],
                    ],
                ],
            ],

            // ============== SLACK (Réaction) ==============
            [
                'name' => 'Slack',
                'auth_type' => 'oauth2',
                'description' => 'Slack workspace integration',
                'actions' => [],
                'reactions' => [
                    [
                        'name' => 'send_slack_message',
                        'identifier' => 'send_slack_message',
                        'description' => 'Envoyer un message Slack',
                        'parameters_schema' => [
                            'channel' => [
                                'type' => 'string',
                                'title' => 'Channel',
                                'description' => 'Nom du channel Slack',
                                'placeholder' => '#general',
                            ],
                            'message' => [
                                'type' => 'string',
                                'title' => 'Message',
                                'description' => 'Le message à envoyer',
                                'placeholder' => 'Notification: {event}',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($services as $serviceData) {
            $service = Service::updateOrCreate(
                ['name' => $serviceData['name']],
                [
                    'auth_type' => $serviceData['auth_type'],
                    'description' => $serviceData['description'],
                ]
            );

            foreach ($serviceData['actions'] as $actionData) {
                Action::updateOrCreate(
                    [
                        'identifier' => $actionData['identifier'],
                        'service_id' => $service->id
                    ],
                    [
                        'name' => $actionData['name'],
                        'description' => $actionData['description'],
                        'parameters_schema' => $actionData['parameters_schema'],
                    ]
                );
            }

            foreach ($serviceData['reactions'] as $reactionData) {
                Reaction::updateOrCreate(
                    [
                        'identifier' => $reactionData['identifier'],
                        'service_id' => $service->id
                    ],
                    [
                        'name' => $reactionData['name'],
                        'description' => $reactionData['description'],
                        'parameters_schema' => $reactionData['parameters_schema'],
                    ]
                );
            }
        }
    }
}