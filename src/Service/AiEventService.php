<?php

namespace App\Service;

use App\Entity\Evenement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiEventService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $geminiApiKey,
    ) {}

    /**
     * Rank events by relevance to recorded user interests.
     *
     * @param Evenement[] $events
     * @return array{recommendations: list<array{id:int,score:int,reason:string}>}
     */
    public function recommendEvents(array $events, array $userInterests): array
    {
        if ([] === $events) {
            return ['recommendations' => []];
        }

        $fallback = ['recommendations' => $this->buildFallbackRecommendations($events, $userInterests)];

        if ('' === trim($this->geminiApiKey)) {
            return $fallback;
        }

        $summaries = array_values(array_map(fn (Evenement $e) => [
            'id' => $e->getId(),
            'titre' => $e->getTitre(),
            'type' => $e->getType(),
            'prix' => $e->getPrix() == 0 ? 'Gratuit' : $e->getPrix().' TND',
            'lieu' => $e->getLieu()?->getVille() ?? $e->getLieu()?->getNom() ?? '',
            'places_restantes' => $e->getPlacesRestantes(),
            'date' => $e->getDateDebut()?->format('d/m/Y') ?? '',
            'description' => mb_substr((string) $e->getDescription(), 0, 150),
        ], $events));

        $prompt =
            "Tu es un algorithme de recommandation d'evenements.\n"
            ."Analyse les interets de l'utilisateur et classe les evenements par pertinence.\n\n"
            ."Interets utilisateur:\n".json_encode($userInterests, JSON_UNESCAPED_UNICODE)."\n\n"
            ."Evenements disponibles:\n".json_encode($summaries, JSON_UNESCAPED_UNICODE)."\n\n"
            ."Retourne uniquement ce JSON:\n"
            .'{"recommendations":[{"id":<int>,"score":<0-100>,"reason":"<raison courte en francais>"}]}'."\n"
            ."Maximum 6 recommandations, triees de la plus pertinente a la moins pertinente.";

        $result = $this->callGemini($prompt, $fallback);
        $normalized = $this->normalizeRecommendations($result['recommendations'] ?? [], $events);

        return ['recommendations' => $normalized !== [] ? $normalized : $fallback['recommendations']];
    }

    /**
     * @return array{performance_score:int,performance_label:string,insights:list<string>,recommendations:list<string>,prediction:string}
     */
    public function analyzeEventStats(Evenement $evenement, array $stats): array
    {
        if ('' === trim($this->geminiApiKey)) {
            return $this->fallbackStats();
        }

        $context = [
            'evenement' => [
                'titre' => $evenement->getTitre(),
                'type' => $evenement->getType(),
                'prix' => $evenement->getPrix(),
                'capacite_max' => $evenement->getCapaciteMax(),
                'statut' => $evenement->getStatut(),
                'date_debut' => $evenement->getDateDebut()?->format('d/m/Y H:i'),
                'lieu' => $evenement->getLieu()?->getNom() ?? 'Non precise',
            ],
            'statistiques' => $stats,
        ];

        $prompt =
            "Tu es un analyste d'evenements expert.\n"
            ."Analyse ces donnees et genere des insights business precis en francais.\n\n"
            ."Donnees:\n".json_encode($context, JSON_UNESCAPED_UNICODE)."\n\n"
            ."Retourne uniquement ce JSON:\n"
            .'{"performance_score":<0-100>,"performance_label":"<Excellent|Bon|Moyen|A ameliorer>",'
            .'"insights":["<insight1>","<insight2>","<insight3>"],'
            .'"recommendations":["<action1>","<action2>"],'
            .'"prediction":"<prediction courte sur le succes de l evenement>"}';

        return $this->callGemini($prompt, $this->fallbackStats());
    }

    private function callGemini(string $prompt, array $fallback): array
    {
        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'x-goog-api-key' => $this->geminiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [[
                        'parts' => [[
                            'text' => $prompt,
                        ]],
                    ]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.4,
                    ],
                ],
                'timeout' => 20,
            ]);

            $data = $response->toArray(false);
            $text = $this->extractGeminiText($data);

            if ('' !== $text && preg_match('/\{.*\}/s', $text, $match)) {
                $decoded = json_decode($match[0], true);
                if (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (\Throwable) {
            // Fall through to fallback response.
        }

        return $fallback;
    }

    private function extractGeminiText(array $data): string
    {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        if (!is_array($parts)) {
            return '';
        }

        $chunks = [];
        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $chunks[] = $part['text'];
            }
        }

        return trim(implode("\n", $chunks));
    }

    private function fallbackStats(): array
    {
        return [
            'performance_score' => 0,
            'performance_label' => 'Indisponible',
            'insights' => ['Service IA Gemini temporairement indisponible. Verifiez la configuration GEMINI_API_KEY.'],
            'recommendations' => [],
            'prediction' => '',
        ];
    }

    /**
     * @param array<int, mixed> $recommendations
     * @param Evenement[] $events
     * @return list<array{id:int,score:int,reason:string}>
     */
    private function normalizeRecommendations(array $recommendations, array $events): array
    {
        $validIds = [];
        foreach ($events as $event) {
            if ($event->getId() !== null) {
                $validIds[$event->getId()] = true;
            }
        }

        $normalized = [];
        foreach ($recommendations as $recommendation) {
            if (!is_array($recommendation)) {
                continue;
            }

            $id = (int) ($recommendation['id'] ?? 0);
            if ($id <= 0 || !isset($validIds[$id])) {
                continue;
            }

            $score = max(0, min(100, (int) ($recommendation['score'] ?? 0)));
            $reason = trim((string) ($recommendation['reason'] ?? ''));

            $normalized[] = [
                'id' => $id,
                'score' => $score,
                'reason' => $reason !== '' ? $reason : 'Selection pertinente selon votre profil.',
            ];
        }

        return array_values(array_slice($normalized, 0, 6));
    }

    /**
     * @param Evenement[] $events
     * @return list<array{id:int,score:int,reason:string}>
     */
    private function buildFallbackRecommendations(array $events, array $userInterests): array
    {
        $preferredTypes = array_map('strtoupper', $this->extractStringValues($userInterests, [
            'types_vus',
            'types_inscrits',
        ]));
        $preferredCities = array_map('mb_strtolower', $this->extractStringValues($userInterests, [
            'villes_inscrites',
        ]));
        $priceSignals = array_map('mb_strtolower', $this->extractStringValues($userInterests, [
            'prix_preference',
            'prix_preference_historique',
        ]));
        $interestTokens = $this->tokenizeStrings($this->extractAllStrings($userInterests));

        $ranked = [];
        foreach ($events as $event) {
            $eventId = $event->getId();
            if ($eventId === null) {
                continue;
            }

            $score = 35;
            $reasons = [];

            $eventType = strtoupper((string) $event->getType());
            if ($eventType !== '' && in_array($eventType, $preferredTypes, true)) {
                $score += 22;
                $reasons[] = 'meme type d\'evenement que vos preferences';
            }

            $city = mb_strtolower((string) ($event->getLieu()?->getVille() ?? $event->getLieu()?->getNom() ?? ''));
            if ($city !== '' && in_array($city, $preferredCities, true)) {
                $score += 18;
                $reasons[] = 'lieu proche de vos choix habituels';
            }

            $priceLabel = $event->getPrix() > 0 ? 'payant' : 'gratuit';
            if (in_array($priceLabel, $priceSignals, true)) {
                $score += 14;
                $reasons[] = $priceLabel === 'gratuit' ? 'correspond a votre preference pour les evenements gratuits' : 'correspond a votre preference de budget';
            }

            $eventTokens = $this->tokenizeStrings([
                (string) $event->getTitre(),
                (string) $event->getDescription(),
                (string) ($event->getLieu()?->getVille() ?? ''),
                (string) ($event->getLieu()?->getNom() ?? ''),
            ]);
            $overlap = count(array_intersect($interestTokens, $eventTokens));
            if ($overlap > 0) {
                $score += min(24, $overlap * 6);
                $reasons[] = 'themes proches de vos recherches recentes';
            }

            $places = $event->getPlacesRestantes();
            if ($places > 0) {
                $score += min(8, $places);
            }

            $ranked[] = [
                'id' => $eventId,
                'score' => max(1, min(100, $score)),
                'reason' => $this->buildFallbackReason($reasons),
            ];
        }

        usort($ranked, static function (array $left, array $right): int {
            return $right['score'] <=> $left['score'];
        });

        return array_values(array_slice($ranked, 0, 6));
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string> $keys
     * @return list<string>
     */
    private function extractStringValues(array $source, array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }

            $value = $source[$key];
            if (is_string($value) && trim($value) !== '') {
                $values[] = trim($value);
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $values[] = trim($item);
                    }
                }
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param array<string, mixed> $source
     * @return list<string>
     */
    private function extractAllStrings(array $source): array
    {
        $values = [];

        foreach ($source as $value) {
            if (is_string($value) && trim($value) !== '') {
                $values[] = trim($value);
                continue;
            }

            if (!is_array($value)) {
                continue;
            }

            foreach ($value as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $values[] = trim($item);
                }
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param list<string> $strings
     * @return list<string>
     */
    private function tokenizeStrings(array $strings): array
    {
        $tokens = [];

        foreach ($strings as $string) {
            $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($string), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($parts as $part) {
                if (mb_strlen($part) >= 3) {
                    $tokens[] = $part;
                }
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @param list<string> $reasons
     */
    private function buildFallbackReason(array $reasons): string
    {
        $reasons = array_values(array_unique(array_filter($reasons)));
        if ($reasons === []) {
            return 'Evenement pertinent parmi ceux disponibles en ce moment.';
        }

        return ucfirst(implode(', ', array_slice($reasons, 0, 2))).'.';
    }
}
