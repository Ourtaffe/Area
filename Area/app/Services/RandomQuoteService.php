<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RandomQuoteService implements ServiceInterface
{
    
    private array $fallbackQuotes = [
        [
            'content' => "Le succès est la somme de petits efforts répétés chaque jour.",
            'author' => "Robert Collier"
        ],
        [
            'content' => "La simplicité est la sophistication ultime.",
            'author' => "Léonard de Vinci"
        ],
        [
            'content' => "Celui qui déplace une montagne commence par déplacer de petites pierres.",
            'author' => "Confucius"
        ],
        [
            'content' => "Chaque jour est une nouvelle chance de faire mieux.",
            'author' => "Anonyme"
        ],
        [
            'content' => "La motivation te lance. L'habitude te fait continuer.",
            'author' => "Jim Ryun"
        ]
    ];

   
    public function checkAction(string $actionName, array $params, ?Carbon $lastExecutedAt, ?int $userId = null): array|false
    {
        Log::info("RandomQuoteService: checking action {$actionName}");

        return match ($actionName) {
            'random_quote_fetch' => $this->checkRandomQuoteFetch($params, $lastExecutedAt),
            'random_quote_daily' => $this->checkDailyQuote($lastExecutedAt),
            default => false,
        };
    }

    /**
     * RandomQuote n'a pas de réactions
     */
    public function executeReaction(string $reactionName, array $params, array $actionData = []): array
    {
        Log::info("RandomQuoteService: no reactions available");
        return ['success' => false, 'error' => 'RandomQuote has no reactions'];
    }

    /**
     * Ici c'est pour verifie et récupère une citation aléatoire
     */
    protected function checkRandomQuoteFetch(array $params, ?Carbon $lastExecutedAt): array|false
    {
        // recup une citation
        $quoteData = $this->getRandomQuote();

        if ($quoteData === null) {
            Log::error("RandomQuoteService: impossible de récupérer une citation.");
            return false;
        }

        // Formate le message
        $message = $this->formatQuote($quoteData);

        Log::info("RandomQuoteService: quote retrieved", [
            'author' => $quoteData['author'],
            'content' => substr($quoteData['content'], 0, 50) . '...'
        ]);

        // Pour retourner les données pour la réaction
        return [
            'message' => $message,
            'quote_content' => $quoteData['content'],
            'quote_author' => $quoteData['author'],
        ];
    }

    /**
     * Ici on verifie si c'est un nouveau jour pour declencher la citation du jour
     */
    protected function checkDailyQuote(?Carbon $lastExecutedAt): array|false
    {
        // Si ca s'execute jamais on declenche
        if (!$lastExecutedAt) {
            return $this->checkRandomQuoteFetch([], null);
        }

        // Pour chekc si c'est un nouveau jour
        $lastDate = $lastExecutedAt->format('Y-m-d');
        $today = now()->format('Y-m-d');

        if ($lastDate !== $today) {
            Log::info("RandomQuoteService: new day, triggering daily quote");
            return $this->checkRandomQuoteFetch([], $lastExecutedAt);
        }

        return false;
    }

    /**
     * getRandomQuote : ici je récupère une citation depuis l'API ou fallback
     * 
     * @return array|null ['content' => '...', 'author' => '...']
     */
    private function getRandomQuote(): ?array
    {
        try {
            $response = Http::timeout(5)->get("https://api.quotable.io/random");

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info("RandomQuoteService: API quote received");
                
                return [
                    'content' => $data['content'] ?? '',
                    'author' => $data['author'] ?? 'Inconnu'
                ];
            } else {
                throw new \Exception("API returned error: " . $response->status());
            }

        } catch (\Exception $e) {
            Log::warning("RandomQuoteService: API failed, using fallback", [
                'error' => $e->getMessage()
            ]);
            
            // Utilise une citation de fallback
            $fallback = $this->fallbackQuotes[array_rand($this->fallbackQuotes)];
            return $fallback;
        }
    }

    /**
     * formatQuote : formate une citation pour l'affichage
     * 
     * @param array $quoteData ['content' => '...', 'author' => '...']
     * @return string
     */
    private function formatQuote(array $quoteData): string
    {
        $content = $quoteData['content'] ?? 'Citation indisponible';
        $author = $quoteData['author'] ?? 'Inconnu';

        return " **Citation du jour**\n\n« {$content} »\n\n— *{$author}*";
    }
}