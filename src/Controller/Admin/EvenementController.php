<?php

namespace App\Controller\Admin;

use App\Entity\Evenement;
use App\Entity\Inscription;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use App\Repository\InscriptionRepository;
use App\Service\AiEventService;
use App\Service\EvenementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/admin/evenements')]
#[IsGranted('ROLE_ADMIN')]
class EvenementController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private EvenementService $evenementService,
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

        $allEvenements = $repository->findWithFilters(
            $filters['q'],
            $filters['statut'],
            $filters['type'],
            $filters['prix'],
            $filters['sort'],
            $filters['order']
        );

        $perPage = 5;
        $totalCount = count($allEvenements);
        $totalPages = max(1, (int) ceil($totalCount / $perPage));
        $page = max(1, min($totalPages, (int) $request->query->get('page', 1)));
        $evenements = array_slice($allEvenements, ($page - 1) * $perPage, $perPage);

        $queryParams = $request->query->all();
        unset($queryParams['page']);
        $queryString = http_build_query($queryParams);

        return $this->render('admin/evenement/index.html.twig', [
            'active' => 'evenements',
            'evenements' => $evenements,
            'filters' => $filters,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'queryString' => $queryString,
        ]);
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

        return $this->render('admin/evenement/show.html.twig', [
            'active' => 'evenements',
            'evenement' => $evenement,
            'stats' => $stats,
        ]);
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

            $uploadedFile = $form->get('imageFile')->getData();
            if ($uploadedFile instanceof UploadedFile) {
                try {
                    $evenement->setImageUrl($this->storeUploadedImage($uploadedFile));
                } catch (FileException) {
                    $form->get('imageFile')->addError(new FormError('Impossible d\'enregistrer l\'image.'));
                    return $this->render('admin/evenement/new.html.twig', [
                        'active' => 'evenements',
                        'form' => $form->createView(),
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
                    'active' => 'evenements',
                    'form' => $form->createView(),
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
                    'active' => 'evenements',
                    'evenement' => $evenement,
                    'form' => $form->createView(),
                ]);
            }

            $uploadedFile = $form->get('imageFile')->getData();
            if ($uploadedFile instanceof UploadedFile) {
                try {
                    $evenement->setImageUrl($this->storeUploadedImage($uploadedFile));
                } catch (FileException) {
                    $form->get('imageFile')->addError(new FormError('Impossible d\'enregistrer l\'image.'));
                    return $this->render('admin/evenement/edit.html.twig', [
                        'active' => 'evenements',
                        'evenement' => $evenement,
                        'form' => $form->createView(),
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
                    'active' => 'evenements',
                    'evenement' => $evenement,
                    'form' => $form->createView(),
                ]);
            }
        }

        return $this->render('admin/evenement/edit.html.twig', [
            'active' => 'evenements',
            'evenement' => $evenement,
            'form' => $form->createView(),
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

    #[Route('/{id<\\d+>}/ai-insights', name: 'app_admin_evenement_ai_insights', methods: ['GET'])]
    public function aiInsights(int $id, EvenementRepository $repository, AiEventService $aiService): Response
    {
        $evenement = $repository->find($id);
        if (!$evenement) {
            return $this->json(['error' => 'Événement introuvable'], 404);
        }

        $stats = $this->evenementService->getStatistiquesEvenement($evenement);
        $result = $aiService->analyzeEventStats($evenement, $stats);

        return $this->json($result);
    }

    #[Route('/{id<\\d+>}/inscriptions', name: 'app_admin_inscriptions', methods: ['GET'])]
    public function gererInscriptions(int $id, EvenementRepository $repository, InscriptionRepository $repo): Response
    {
        $evenement = $repository->find($id);
        if (!$evenement) {
            $this->addFlash('error', 'Événement introuvable.');
            return $this->redirectToRoute('app_admin_evenements');
        }

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

    #[Route('/inscription/{id}/accepter', name: 'app_admin_inscription_accepter', methods: ['POST'])]
    public function accepterInscription(Inscription $inscription, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('accepter' . $inscription->getId(), $request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
        } else {
            try {
                $this->evenementService->accepterInscription($inscription);
                $this->addFlash('success', sprintf(
                    '✅ Inscription de %s acceptée. En attente de paiement.',
                    $inscription->getUser()->getPrenom()
                ));
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ ' . $e->getMessage());
            }
        }

        $evenement = $inscription->getEvenement();
        return $this->redirectToRoute('app_admin_inscriptions', ['id' => $evenement->getId()]);
    }

    #[Route('/inscription/{id}/refuser', name: 'app_admin_inscription_refuser', methods: ['POST'])]
    public function refuserInscription(Inscription $inscription, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('refuser' . $inscription->getId(), $request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
        } else {
            try {
                $motif = $request->get('motif', 'Refuse par l\'administrateur');
                $this->evenementService->refuserInscription($inscription, $motif);
                $this->addFlash('warning', sprintf(
                    '❌ Inscription de %s refusée.',
                    $inscription->getUser()->getPrenom()
                ));
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ ' . $e->getMessage());
            }
        }

        $evenement = $inscription->getEvenement();
        return $this->redirectToRoute('app_admin_inscriptions', ['id' => $evenement->getId()]);
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

        $evenement = $inscription->getEvenement();
        return $this->redirectToRoute('app_admin_inscriptions', ['id' => $evenement->getId()]);
    }

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