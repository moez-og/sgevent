<?php

namespace App\Controller\Admin;

use App\Entity\Evenement;
use App\Entity\Inscription;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use App\Repository\InscriptionRepository;
use App\Service\EvenementService;
use App\Service\NotionSyncService;
use App\Service\WeatherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Evenement Controller (v2).
 *
 * Changements v2 :
 *  - Suppression de la logique "accepter / refuser" en amont (validation admin supprimée)
 *  - Les routes accepter/refuser sont conservées pour compat mais renvoient un flash info
 *  - Nouvelle route JSON /{id}/weather-cards pour le rendu météo style "image de référence"
 */
#[Route('/admin/evenements')]
#[IsGranted('ROLE_ADMIN')]
class EvenementController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private EvenementService $evenementService,
        private WeatherService $weatherService
    ) {}

    #[Route('', name: 'app_admin_evenements', methods: ['GET'])]
    public function index(Request $request, EvenementRepository $repository): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'statut' => (string) $request->query->get('statut', ''),
            'type' => (string) $request->query->get('type', ''),
            'prix' => (string) $request->query->get('prix', ''),
            'sort' => (string) $request->query->get('sort', ''),
            'order' => (string) $request->query->get('order', ''),
        ];

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 5;
        $total = $repository->countWithFilters(
            $filters['q'], $filters['statut'], $filters['type'], $filters['prix']
        );
        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;

        $evenements = $repository->findWithFilters(
            $filters['q'], $filters['statut'], $filters['type'], $filters['prix'],
            $filters['sort'], $filters['order'], $limit, $offset
        );

        return $this->render('admin/evenement/index.html.twig', [
            'active' => 'evenements',
            'evenements' => $evenements,
            'filters' => $filters,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_results' => $total,
            'limit' => $limit,
        ]);
    }

    #[Route('/calendar', name: 'app_admin_evenements_calendar', methods: ['GET'])]
    public function calendar(NotionSyncService $syncService): Response
    {
        $status = 'success';
        $message = 'Synchronisation Notion terminee.';

        try {
            $result = $syncService->sync();
            $message = sprintf(
                'Synchronisation terminee. Evenements crees: %d, pages liees: %d, pages creees: %d, pages mises a jour: %d.',
                $result['created_events'], $result['linked_pages'], $result['created_pages'], $result['updated_pages']
            );
        } catch (\Throwable $e) {
            $status = 'error';
            $message = 'Synchronisation echouee: ' . $e->getMessage();
        }

        return $this->render('admin/evenement/calendar.html.twig', [
            'active' => 'evenements',
            'sync_status' => $status,
            'sync_message' => $message,
        ]);
    }

    #[Route('/calendar-data', name: 'app_admin_evenements_calendar_data', methods: ['GET'])]
    public function calendarData(EvenementRepository $repository): JsonResponse
    {
        $events = $repository->findAll();
        $payload = [];

        foreach ($events as $event) {
            $start = $event->getDateDebut();
            $end = $event->getDateFin();
            $status = $event->getStatut();

            $payload[] = [
                'id' => $event->getId(),
                'title' => $event->getTitre(),
                'start' => $start?->format('c'),
                'end' => $end?->format('c'),
                'url' => $this->generateUrl('app_admin_evenement_show', ['id' => $event->getId()]),
                'classNames' => [$this->mapStatusClass($status)],
                'extendedProps' => [
                    'lieu' => $event->getLieu()?->getNom(),
                    'prix' => $event->getPrix(),
                    'type' => $event->getType(),
                ],
            ];
        }

        return $this->json(['ok' => true, 'events' => $payload]);
    }

    #[Route('/notion-sync', name: 'app_admin_evenements_notion_sync', methods: ['POST'])]
    public function notionSync(Request $request, NotionSyncService $syncService): Response
    {
        if (!$this->isCsrfTokenValid('notion_sync', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_evenements');
        }

        try {
            $result = $syncService->sync();
            $this->addFlash('success', sprintf(
                'Sync Notion termine. Evenements crees: %d, pages liees: %d, pages creees: %d, pages mises a jour: %d.',
                $result['created_events'], $result['linked_pages'], $result['created_pages'], $result['updated_pages']
            ));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Sync Notion echoue: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_evenements');
    }

    #[Route('/{id<\\d+>}', name: 'app_admin_evenement_show', methods: ['GET'])]
    public function show(int $id, EvenementRepository $repository): Response
    {
        $evenement = $repository->find($id);
        if (!$evenement) {
            $this->addFlash('error', 'Événement introuvable.');
            return $this->redirectToRoute('app_admin_evenements');
        }

        $stats = $this->evenementService->getStatistiquesEvenement($evenement);
        $weather = $this->weatherService->getForecastForEvenement($evenement);

        return $this->render('admin/evenement/show.html.twig', [
            'active' => 'evenements',
            'evenement' => $evenement,
            'stats' => $stats,
            'weather' => $weather,
        ]);
    }

    #[Route('/{id<\d+>}/weather-summary', name: 'app_admin_evenement_weather_summary', methods: ['GET'])]
    public function weatherSummary(int $id, EvenementRepository $repository): JsonResponse
    {
        $evenement = $repository->find($id);
        if (!$evenement) {
            return $this->json(['ok' => false, 'message' => 'Événement introuvable.'], 404);
        }

        $weather = $this->weatherService->getForecastForEvenement($evenement);
        if (($weather['status'] ?? '') !== 'ok' || empty($weather['days'])) {
            return $this->json(['ok' => false, 'message' => 'Meteo indisponible.']);
        }

        $start = $evenement->getDateDebut()?->format('Y-m-d') ?? '';
        $end = $evenement->getDateFin()?->format('Y-m-d') ?? $start;
        $days = array_filter($weather['days'], static fn(array $day): bool =>
            ($day['date'] ?? '') >= $start && ($day['date'] ?? '') <= $end
        );
        if (empty($days)) {
            $days = $weather['days'];
        }

        $precipValues = array_values(array_filter(array_column($days, 'precip_prob'), static fn($v) => $v !== null));
        $windValues = array_values(array_filter(array_column($days, 'wind_max'), static fn($v) => $v !== null));
        $tempMinValues = array_values(array_filter(array_column($days, 'temp_min'), static fn($v) => $v !== null));
        $tempMaxValues = array_values(array_filter(array_column($days, 'temp_max'), static fn($v) => $v !== null));
        $participationValues = array_values(array_filter(array_column($days, 'participation'), static fn($v) => $v !== null));

        $avg = static fn(array $values): ?int => $values === [] ? null : (int) round(array_sum($values) / count($values));

        return $this->json([
            'ok' => true,
            'precip_avg' => $avg($precipValues),
            'wind_avg' => $avg($windValues),
            'temp_min_avg' => $avg($tempMinValues),
            'temp_max_avg' => $avg($tempMaxValues),
            'participation_avg' => $avg($participationValues),
        ]);
    }

    /**
     * 🌤 Nouvelle route : renvoie la météo au format "cartes" (style image de référence).
     * Utilisée par le front pour afficher jusqu'à 6 jours sous forme de cards verticales.
     */
    #[Route('/{id<\d+>}/weather-cards', name: 'app_admin_evenement_weather_cards', methods: ['GET'])]
    public function weatherCards(int $id, EvenementRepository $repository): JsonResponse
    {
        $evenement = $repository->find($id);
        if (!$evenement) {
            return $this->json(['ok' => false, 'message' => 'Événement introuvable.'], 404);
        }

        $weather = $this->weatherService->getForecastForEvenement($evenement);
        if (($weather['status'] ?? '') !== 'ok' || empty($weather['days'])) {
            return $this->json(['ok' => false, 'message' => 'Meteo indisponible.']);
        }

        $cards = [];
        foreach (array_slice($weather['days'], 0, 6) as $day) {
            $code = $day['weather_code'] ?? null;
            $temp = $day['temp_max'] ?? null;
            if ($temp === null && ($day['temp_min'] ?? null) !== null) {
                $temp = $day['temp_min'];
            }

            $cards[] = [
                'date' => $day['date'] ?? null,
                'date_fr' => isset($day['date']) ? $this->formatDateFr($day['date']) : null,
                'temp' => $temp !== null ? (int) round($temp) : null,
                'temp_min' => $day['temp_min'] ?? null,
                'temp_max' => $day['temp_max'] ?? null,
                'label' => $day['label'] ?? 'Inconnu',
                'icon_slug' => $this->iconSlugFromCode($code),
                'gradient' => $this->gradientFromCode($code),
                'precip_prob' => $day['precip_prob'] ?? null,
                'wind_max' => $day['wind_max'] ?? null,
                'participation' => $day['participation'] ?? null,
            ];
        }

        return $this->json([
            'ok' => true,
            'location' => $weather['location'] ?? null,
            'cards' => $cards,
        ]);
    }

    /**
     * Mappe un code WMO → slug d'icône (utilisé par le front pour rendre les icônes SVG).
     */
    private function iconSlugFromCode(?int $code): string
    {
        return match (true) {
            $code === null         => 'unknown',
            $code === 0            => 'sunny',
            $code >= 1 && $code <= 3  => 'partly-sunny',
            $code >= 45 && $code <= 48 => 'fog',
            $code >= 51 && $code <= 57 => 'drizzle',
            $code >= 61 && $code <= 67 => 'rain',
            $code >= 71 && $code <= 77 => 'snow',
            $code >= 80 && $code <= 82 => 'showers',
            $code >= 95 && $code <= 99 => 'thunderstorm',
            default                => 'cloudy',
        };
    }

    /**
     * Dégradé bleu/violet à appliquer à la carte, selon la nature du temps.
     */
    private function gradientFromCode(?int $code): string
    {
        return match (true) {
            $code === null          => 'from-slate-500 to-slate-700',
            $code === 0             => 'sunny',         // bleu ciel clair
            $code >= 1 && $code <= 3 => 'partly',       // bleu moyen
            $code >= 95 && $code <= 99 => 'storm',      // bleu foncé
            $code >= 61 && $code <= 82 => 'rain',       // bleu-violet
            default                  => 'cloudy',       // bleu nuit
        };
    }

    private function formatDateFr(string $ymd): string
    {
        try {
            $d = new \DateTimeImmutable($ymd);
            $days = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
            return $days[(int) $d->format('w')] . ' ' . $d->format('d/m');
        } catch (\Throwable) {
            return $ymd;
        }
    }

    private function mapStatusClass(string $status): string
    {
        return match ($status) {
            Evenement::STATUT_OUVERT => 'event-status-open',
            Evenement::STATUT_FERME => 'event-status-closed',
            Evenement::STATUT_ANNULE => 'event-status-cancelled',
            default => 'event-status-default',
        };
    }

    #[Route('/generate-image', name: 'admin_evenement_generate_image', methods: ['POST'])]
    public function generateImage(Request $request): JsonResponse
    {
        $prompt = trim((string) $request->request->get('prompt', ''));
        if ($prompt === '') {
            return $this->json(['ok' => false, 'message' => 'Prompt vide.'], 400);
        }

        $seed = random_int(1, 999983);
        $url  = 'https://image.pollinations.ai/prompt/'
            . rawurlencode(mb_substr($prompt, 0, 350))
            . '?model=flux&width=1024&height=576&nologo=true&seed=' . $seed;

        set_time_limit(90);
        $ctx       = stream_context_create(['http' => ['timeout' => 60]]);
        $imageData = @file_get_contents($url, false, $ctx);

        if ($imageData === false || strlen($imageData) < 1000) {
            return $this->json(['ok' => false, 'message' => 'Génération échouée. Réessayez.'], 502);
        }

        $targetDir = $this->getParameter('kernel.project_dir') . '/public/uploads/evenements';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $fileName = 'ai-' . uniqid() . '.jpg';
        file_put_contents($targetDir . '/' . $fileName, $imageData);

        return $this->json(['ok' => true, 'path' => 'uploads/evenements/' . $fileName]);
    }

    #[Route('/new', name: 'app_admin_evenements_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->validateEvenementInput($evenement, $form)) {
                return $this->render('admin/evenement/new.html.twig', [
                    'active' => 'evenements',
                    'form' => $form->createView(),
                ]);
            }

            $generatedImage = trim((string) $request->request->get('generated_image', ''));
            if ($generatedImage !== '') {
                $evenement->setImageUrl($generatedImage);
            }

            $uploadedFile = $form->get('imageFile')->getData();
            if ($uploadedFile instanceof UploadedFile) {
                try {
                    $evenement->setImageUrl($this->storeUploadedImage($uploadedFile));
                } catch (FileException) {
                    $form->get('imageFile')->addError(new FormError('Impossible d\'enregistrer l\'image.'));
                    return $this->render('admin/evenement/new.html.twig', [
                        'active' => 'evenements', 'form' => $form->createView(),
                    ]);
                }
            }

            try {
                $this->em->persist($evenement);
                $this->em->flush();
                $this->addFlash('success', 'Événement créé avec succès.');
                return $this->redirectToRoute('app_admin_evenements');
            } catch (\Throwable) {
                $this->addFlash('error', 'Impossible de créer l\'événement. Vérifiez les champs obligatoires puis réessayez.');
                return $this->render('admin/evenement/new.html.twig', [
                    'active' => 'evenements', 'form' => $form->createView(),
                ]);
            }
        }

        return $this->render('admin/evenement/new.html.twig', [
            'active' => 'evenements',
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id<\\d+>}/edit', name: 'app_admin_evenements_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id, EvenementRepository $repository): Response
    {
        $evenement = $repository->find($id);
        if (!$evenement) {
            $this->addFlash('error', 'Événement introuvable.');
            return $this->redirectToRoute('app_admin_evenements');
        }

        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->validateEvenementInput($evenement, $form)) {
                return $this->render('admin/evenement/edit.html.twig', [
                    'active' => 'evenements', 'evenement' => $evenement, 'form' => $form->createView(),
                ]);
            }

            // Support image IA aussi pour l'édition
            $generatedImage = trim((string) $request->request->get('generated_image', ''));
            if ($generatedImage !== '') {
                $evenement->setImageUrl($generatedImage);
            }

            $uploadedFile = $form->get('imageFile')->getData();
            if ($uploadedFile instanceof UploadedFile) {
                try {
                    $evenement->setImageUrl($this->storeUploadedImage($uploadedFile));
                } catch (FileException) {
                    $form->get('imageFile')->addError(new FormError('Impossible d\'enregistrer l\'image.'));
                    return $this->render('admin/evenement/edit.html.twig', [
                        'active' => 'evenements', 'evenement' => $evenement, 'form' => $form->createView(),
                    ]);
                }
            }

            try {
                $this->em->flush();
                $this->addFlash('success', 'Événement modifié avec succès.');
                return $this->redirectToRoute('app_admin_evenements');
            } catch (\Throwable) {
                $this->addFlash('error', 'Impossible de modifier l\'événement. Vérifiez les champs obligatoires puis réessayez.');
                return $this->render('admin/evenement/edit.html.twig', [
                    'active' => 'evenements', 'evenement' => $evenement, 'form' => $form->createView(),
                ]);
            }
        }

        return $this->render('admin/evenement/edit.html.twig', [
            'active' => 'evenements', 'evenement' => $evenement, 'form' => $form->createView(),
        ]);
    }

    #[Route('/{id<\\d+>}/delete', name: 'app_admin_evenements_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, EvenementRepository $repository): Response
    {
        $evenement = $repository->find($id);
        if (!$evenement) {
            $this->addFlash('error', 'Événement introuvable.');
            return $this->redirectToRoute('app_admin_evenements');
        }

        if ($this->isCsrfTokenValid('delete'.$evenement->getId(), $request->get('_token'))) {
            $this->em->remove($evenement);
            $this->em->flush();
            $this->addFlash('success', 'Événement supprimé avec succès.');
        }

        return $this->redirectToRoute('app_admin_evenements');
    }

    // ==================== GESTION DES INSCRIPTIONS ====================

    #[Route('/{id<\\d+>}/inscriptions', name: 'app_admin_inscriptions', methods: ['GET'])]
    public function gererInscriptions(int $id, EvenementRepository $repository, InscriptionRepository $repo): Response
    {
        $evenement = $repository->find($id);
        if (!$evenement) {
            $this->addFlash('error', 'Événement introuvable.');
            return $this->redirectToRoute('app_admin_evenements');
        }

        // v2 : plus d'EN_ATTENTE par défaut, mais on garde pour compat historique
        $inscriptionsEnAttente = $repo->findInscriptionsEnAttente($evenement);
        $inscriptions = $repo->findBy(['evenement' => $evenement], ['dateCreation' => 'DESC']);
        $stats = $this->evenementService->getStatistiquesEvenement($evenement);

        return $this->render('admin/evenement/inscriptions.html.twig', [
            'active' => 'evenements',
            'evenement' => $evenement,
            'inscriptions_en_attente' => $inscriptionsEnAttente,
            'inscriptions' => $inscriptions,
            'stats' => $stats,
        ]);
    }

    /**
     * @deprecated v2 : validation admin supprimée. Route conservée pour compat.
     */
    #[Route('/inscription/{id}/accepter', name: 'app_admin_inscription_accepter', methods: ['POST'])]
    public function accepterInscription(Inscription $inscription, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('accepter' . $inscription->getId(), $request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
        } else {
            try {
                $this->evenementService->accepterInscription($inscription);
                $this->addFlash('info', 'Les inscriptions sont désormais auto-confirmées. Statut mis à jour.');
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ ' . $e->getMessage());
            }
        }
        return $this->redirectToRoute('app_admin_inscriptions', ['id' => $inscription->getEvenement()->getId()]);
    }

    /**
     * Rejet explicite d'une inscription (abus, fraude, doublon…). Toujours utile.
     */
    #[Route('/inscription/{id}/refuser', name: 'app_admin_inscription_refuser', methods: ['POST'])]
    public function refuserInscription(Inscription $inscription, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('refuser' . $inscription->getId(), $request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
        } else {
            try {
                $motif = (string) $request->get('motif', 'Refusé par l\'administrateur');
                $this->evenementService->refuserInscription($inscription, $motif);
                $this->addFlash('warning', sprintf(
                    '❌ Inscription de %s refusée.',
                    $inscription->getUser()->getPrenom()
                ));
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ ' . $e->getMessage());
            }
        }
        return $this->redirectToRoute('app_admin_inscriptions', ['id' => $inscription->getEvenement()->getId()]);
    }

    #[Route('/inscription/{id}/remboursement', name: 'app_admin_inscription_rembourser', methods: ['POST'])]
    public function rembourserInscription(Inscription $inscription, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('rembourser' . $inscription->getId(), $request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
        } else {
            try {
                $this->evenementService->rembourserInscription($inscription);
                $this->addFlash('info', sprintf(
                    '💰 Inscription de %s remboursée.',
                    $inscription->getUser()->getPrenom()
                ));
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ ' . $e->getMessage());
            }
        }
        return $this->redirectToRoute('app_admin_inscriptions', ['id' => $inscription->getEvenement()->getId()]);
    }

    // ==================== HELPERS ====================

    private function storeUploadedImage(UploadedFile $uploadedFile): string
    {
        $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', $originalName);
        $safeName = $safeName !== '' ? strtolower($safeName) : 'event';
        $extension = $uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension() ?: 'jpg';
        $fileName = $safeName.'-'.uniqid().'.'.$extension;
        $targetDir = $this->getParameter('kernel.project_dir').'/public/uploads/evenements';

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $uploadedFile->move($targetDir, $fileName);

        return 'uploads/evenements/'.$fileName;
    }

    private function validateEvenementInput(Evenement $evenement, FormInterface $form): bool
    {
        $isValid = true;

        $titre = trim((string) $evenement->getTitre());
        if ($titre === '' || mb_strlen($titre) < 3) {
            $form->get('titre')->addError(new FormError('Le titre doit contenir au moins 3 caractères.'));
            $isValid = false;
        }

        $dateDebut = $evenement->getDateDebut();
        $dateFin = $evenement->getDateFin();
        $now = new \DateTimeImmutable('now');

        if ($dateDebut !== null && $dateDebut < $now) {
            $form->get('dateDebut')->addError(new FormError('La date de début doit être dans le futur.'));
            $isValid = false;
        }

        if ($dateDebut !== null && $dateFin !== null && $dateFin <= $dateDebut) {
            $form->get('dateFin')->addError(new FormError('La date de fin doit être après la date de début.'));
            $isValid = false;
        }

        if ($evenement->getCapaciteMax() === null || $evenement->getCapaciteMax() < 1) {
            $form->get('capaciteMax')->addError(new FormError('La capacité maximale doit être au moins 1.'));
            $isValid = false;
        }

        if ($evenement->getPrix() < 0) {
            $form->get('prix')->addError(new FormError('Le prix ne peut pas être négatif.'));
            $isValid = false;
        }

        if (!in_array($evenement->getType(), Evenement::TYPES_VALIDES, true)) {
            $form->get('type')->addError(new FormError('Type d\'événement invalide.'));
            $isValid = false;
        }

        if (!in_array($evenement->getStatut(), Evenement::STATUTS_VALIDES, true)) {
            $form->get('statut')->addError(new FormError('Statut invalide.'));
            $isValid = false;
        }

        return $isValid;
    }
}
