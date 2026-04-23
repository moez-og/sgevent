<?php

namespace App\Service;

use App\Entity\Evenement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiEventService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL   = 'claude-haiku-4-5-20251001';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $anthropicApiKey,
    ) {}

    /**
     * Rank events by relevance to recorded user interests.
     *
     * @param Evenement[] $events
     * @return array{recommendations: list<array{id:int,score:int,reason:string}>}
     */
    public function recommendEvents(array $events, array $userInterests): array
    {
        if (empty($events) || $this->anthropicApiKey === '') {
            return ['recommendations' => []];
        }

        $summaries = array_values(array_map(fn(Evenement $e) => [
            'id'              => $e->getId(),
            'titre'           => $e->getTitre(),
            'type'            => $e->getType(),
            'prix'            => $e->getPrix() == 0 ? 'Gratuit' : $e->getPrix().' TND',
            'lieu'            => $e->getLieu()?->getVille() ?? $e->getLieu()?->getNom() ?? '',
            'places_restantes'=> $e->getPlacesRestantes(),
            'date'            => $e->getDateDebut()?->format('d/m/Y') ?? '',
            'description'     => mb_substr((string) $e->getDescription(), 0, 150),
        ], $events));

        $prompt =
            "Tu es un algorithme de recommandation d'événements. Analyse les intérêts de l'utilisateur et classe les événements par pertinence.\n\n"
            ."Intérêts utilisateur:\n".json_encode($userInterests, JSON_UNESCAPED_UNICODE)."\n\n"
            ."Événements disponibles:\n".json_encode($summaries, JSON_UNESCAPED_UNICODE)."\n\n"
            ."Retourne UNIQUEMENT ce JSON (sans markdown, sans explication):\n"
            .'{"recommendations":[{"id":<int>,"score":<0-100>,"reason":"<raison courte en français>"}]}'."\n"
            ."Maximum 6 recommandations, les plus pertinentes en premier.";

        return $this->callClaude($prompt, ['recommendations' => []]);
    }

    /**
     * Generate AI-powered business insights from event statistics.
     *
     * @return array{performance_score:int,performance_label:string,insights:list<string>,recommendations:list<string>,prediction:string}
     */
    public function analyzeEventStats(Evenement $evenement, array $stats): array
    {
        if ($this->anthropicApiKey === '') {
            return $this->fallbackStats();
        }

        $context = [
            'evenement' => [
                'titre'       => $evenement->getTitre(),
                'type'        => $evenement->getType(),
                'prix'        => $evenement->getPrix(),
                'capacite_max'=> $evenement->getCapaciteMax(),
                'statut'      => $evenement->getStatut(),
                'date_debut'  => $evenement->getDateDebut()?->format('d/m/Y H:i'),
                'lieu'        => $evenement->getLieu()?->getNom() ?? 'Non précisé',
            ],
            'statistiques' => $stats,
        ];

        $prompt =
            "Tu es un analyste d'événements expert. Analyse ces données et génère des insights business précis en français.\n\n"
            ."Données:\n".json_encode($context, JSON_UNESCAPED_UNICODE)."\n\n"
            ."Retourne UNIQUEMENT ce JSON (sans markdown):\n"
            .'{"performance_score":<0-100>,"performance_label":"<Excellent|Bon|Moyen|À améliorer>",'
            .'"insights":["<insight1>","<insight2>","<insight3>"],'
            .'"recommendations":["<action1>","<action2>"],'
            .'"prediction":"<prédiction courte sur le succès de l\'événement>"}';

        return $this->callClaude($prompt, $this->fallbackStats());
    }

    private function callClaude(string $prompt, array $fallback): array
    {
        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'x-api-key'         => $this->anthropicApiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'json' => [
                    'model'      => self::MODEL,
                    'max_tokens' => 1024,
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                ],
                'timeout' => 20,
            ]);

            $data = $response->toArray(false);
            $text = trim($data['content'][0]['text'] ?? '');

            if (preg_match('/\{.*\}/s', $text, $m)) {
                $decoded = json_decode($m[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (\Throwable) {
            // Silently fall through
        }

        return $fallback;
    }

    private function fallbackStats(): array
    {
        return [
            'performance_score' => 0,
            'performance_label' => 'Indisponible',
            'insights'          => ['Service IA temporairement indisponible. Vérifiez la configuration ANTHROPIC_API_KEY.'],
            'recommendations'   => [],
            'prediction'        => '',
        ];
    }
}
