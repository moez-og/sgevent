<?php

namespace App\Controller\Front;

use App\Service\RecommendationService;
use App\Service\WeatherService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints de recommandation intelligente d'événements (front-office).
 *
 * - Page dédiée : GET /evenements/recommandations
 * - Fragment JSON (pour bouton "Voir les recommandations" inline) : GET /evenements/recommandations/json
 */
#[Route('/evenements')]
class RecommendationController extends AbstractController
{
    public function __construct(
        private readonly RecommendationService $recommendationService,
        private readonly WeatherService $weatherService,
    ) {}

    /**
     * Page dédiée aux recommandations personnalisées.
     */
    #[Route('/recommandations', name: 'app_evenements_recommandations', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function index(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $limit = max(4, min(24, (int) $request->query->get('limit', 12)));

        $events = $this->recommendationService->getRecommendations($user, $limit);

        return $this->render('front/evenement/recommendations.html.twig', [
            'active' => 'evenements',
            'events' => $events,
            'interests_analysis' => $this->recommendationService->getLastInterestsAnalysis(),
            'interests_map' => $this->recommendationService->getLastInterestsMap(),
            'mode' => $this->recommendationService->getLastMode(),
        ]);
    }

    /**
     * Retourne les recommandations en JSON (léger, pour bouton "Voir les suggestions" sur /evenements).
     * Inclut la météo compacte pour chaque événement pour éviter un second aller-retour.
     */
    #[Route('/recommandations/json', name: 'app_evenements_recommandations_json', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function json(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $limit = max(3, min(12, (int) $request->query->get('limit', 6)));

        $events = $this->recommendationService->getRecommendations($user, $limit);

        $payload = [];
        foreach ($events as $ev) {
            // Météo facultative — on ne laisse pas la requête échouer si l'API est down
            $weatherLabel = null;
            $weatherTemp = null;
            try {
                $forecast = $this->weatherService->getForecastForEvenement($ev);
                if (($forecast['status'] ?? '') === 'ok' && !empty($forecast['days'])) {
                    $startDate = $ev->getDateDebut()?->format('Y-m-d');
                    $target = null;
                    foreach ($forecast['days'] as $day) {
                        if (($day['date'] ?? '') === $startDate) {
                            $target = $day;
                            break;
                        }
                    }
                    $target = $target ?? $forecast['days'][0];
                    $weatherLabel = $target['label'] ?? null;
                    $tMax = $target['temp_max'] ?? null;
                    $tMin = $target['temp_min'] ?? null;
                    if ($tMax !== null && $tMin !== null) {
                        $weatherTemp = (int) round(($tMax + $tMin) / 2);
                    } elseif ($tMax !== null) {
                        $weatherTemp = (int) round($tMax);
                    }
                }
            } catch (\Throwable) {
                // on ignore, météo non bloquante
            }

            $payload[] = [
                'id' => $ev->getId(),
                'titre' => $ev->getTitre(),
                'description' => $ev->getDescription() ? mb_substr($ev->getDescription(), 0, 160) : '',
                'date_debut' => $ev->getDateDebut()?->format('c'),
                'date_debut_fr' => $ev->getDateDebut()?->format('d/m/Y H:i'),
                'prix' => $ev->getPrix(),
                'type' => $ev->getType(),
                'statut' => $ev->getStatut(),
                'places_restantes' => $ev->getPlacesRestantes(),
                'lieu' => $ev->getLieu()?->getNom(),
                'ville' => $ev->getLieu()?->getVille(),
                'image_url' => $this->normalizeImageUrl($ev->getImageUrl()),
                'url' => $this->generateUrl('app_evenement_show', ['id' => $ev->getId()]),
                'weather' => [
                    'label' => $weatherLabel,
                    'temp' => $weatherTemp,
                ],
            ];
        }

        return $this->json([
            'ok' => true,
            'mode' => $this->recommendationService->getLastMode(),
            'analysis' => $this->recommendationService->getLastInterestsAnalysis(),
            'interests' => array_keys($this->recommendationService->getLastInterestsMap()),
            'events' => $payload,
        ]);
    }

    private function normalizeImageUrl(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') return null;
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://') || str_starts_with($raw, 'data:image/')) {
            return $raw;
        }
        if (str_starts_with($raw, '/')) {
            return $raw;
        }
        if (str_starts_with($raw, 'theme/') || str_starts_with($raw, 'uploads/')) {
            return '/' . ltrim($raw, '/');
        }
        return '/uploads/evenements/' . ltrim($raw, '/');
    }
}
