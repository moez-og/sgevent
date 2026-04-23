<?php

namespace App\Controller;

use App\Entity\Lieu;
use App\Enum\LieuCategorie;
use App\Enum\LieuType as LieuTypeEnum;
use App\Form\LieuType;
use App\Repository\LieuRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_ADMIN')]
class LieuController extends AbstractController
{
    private const PER_PAGE = 12;
    private const WEEK_DAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    #[Route('/admin/lieu', name: 'app_admin_lieu_index', methods: ['GET'])]
    #[Route('/admin/lieux', name: 'app_admin_lieux', methods: ['GET'])]
    public function index(Request $request, LieuRepository $lieuRepository): Response
    {
        $filters = $this->extractFilters($request);
        $page = max(1, (int) $request->query->get('page', 1));
        $pagination = $lieuRepository->paginateFiltered($filters, $page, self::PER_PAGE);
        $totalItems = count($pagination);
        $pageCount = max(1, (int) ceil($totalItems / self::PER_PAGE));

        return $this->render('lieu/index.html.twig', [
            'active' => 'lieux',
            'lieux' => iterator_to_array($pagination, false),
            'searchQuery' => $filters['q'],
            'currentCategorie' => $filters['categorie']?->value,
            'currentType' => $filters['type']?->value,
            'currentSort' => $filters['sort'],
            'currentDirection' => $filters['dir'],
            'page' => $page,
            'pageCount' => $pageCount,
            'totalItems' => $totalItems,
            'perPage' => self::PER_PAGE,
            'categorieCases' => LieuCategorie::cases(),
            'typeCases' => LieuTypeEnum::cases(),
        ]);
    }

    #[Route('/admin/lieu/new', name: 'app_admin_lieu_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, Connection $connection): Response
    {
        $lieu = new Lieu();
        $horaires = $this->defaultHorairesFormData();
        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $horaires = $this->normalizeHorairesInputForView($request->request->all('horaires'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $normalizedHoraires = $this->validateAndNormalizeHoraires($horaires, $form);
            if ($normalizedHoraires === null) {
                $this->addFlash('error', 'Veuillez corriger les erreurs du formulaire.');
                return $this->render('lieu/new.html.twig', [
                    'active' => 'lieux',
                    'lieu' => $lieu,
                    'form' => $form->createView(),
                    'horaires' => $horaires,
                ]);
            }

            if (!$this->handleLieuImageUpload($form, $lieu, $slugger)) {
                return $this->render('lieu/new.html.twig', [
                    'active' => 'lieux',
                    'lieu' => $lieu,
                    'form' => $form->createView(),
                    'horaires' => $horaires,
                ]);
            }

            $entityManager->persist($lieu);
            $entityManager->flush();
            $this->saveLieuHoraires($connection, (int) $lieu->getId(), $normalizedHoraires);

            $this->addFlash('success', 'Lieu créé avec succès.');

            return $this->redirectToRoute('app_admin_lieu_show', ['id' => $lieu->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('lieu/new.html.twig', [
            'active' => 'lieux',
            'lieu' => $lieu,
            'form' => $form->createView(),
            'horaires' => $horaires,
        ]);
    }

    #[Route('/admin/lieu/{id}', name: 'app_admin_lieu_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(int $id, LieuRepository $lieuRepository): Response
    {
        $lieu = $lieuRepository->findDetailed($id);

        if ($lieu === null) {
            throw $this->createNotFoundException('Lieu introuvable.');
        }

        return $this->render('lieu/show.html.twig', [
            'active' => 'lieux',
            'lieu' => $lieu,
        ]);
    }

    #[Route('/admin/lieu/{id}/edit', name: 'app_admin_lieu_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(int $id, Request $request, LieuRepository $lieuRepository, EntityManagerInterface $entityManager, SluggerInterface $slugger, Connection $connection): Response
    {
        $lieu = $lieuRepository->findDetailed($id);

        if ($lieu === null) {
            throw $this->createNotFoundException('Lieu introuvable.');
        }

        $existingImageUrl = $lieu->getImageUrl();
        $horaires = $this->loadHorairesFormData($connection, $id);
        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $horaires = $this->normalizeHorairesInputForView($request->request->all('horaires'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $normalizedHoraires = $this->validateAndNormalizeHoraires($horaires, $form);
            if ($normalizedHoraires === null) {
                $this->addFlash('error', 'Veuillez corriger les erreurs du formulaire.');
                return $this->render('lieu/edit.html.twig', [
                    'active' => 'lieux',
                    'lieu' => $lieu,
                    'form' => $form->createView(),
                    'horaires' => $horaires,
                ]);
            }

            if (!$this->handleLieuImageUpload($form, $lieu, $slugger, $existingImageUrl)) {
                return $this->render('lieu/edit.html.twig', [
                    'active' => 'lieux',
                    'lieu' => $lieu,
                    'form' => $form->createView(),
                    'horaires' => $horaires,
                ]);
            }

            $entityManager->flush();
            $this->saveLieuHoraires($connection, (int) $lieu->getId(), $normalizedHoraires);

            $this->addFlash('success', 'Lieu modifié avec succès.');

            return $this->redirectToRoute('app_admin_lieu_show', ['id' => $lieu->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('lieu/edit.html.twig', [
            'active' => 'lieux',
            'lieu' => $lieu,
            'form' => $form->createView(),
            'horaires' => $horaires,
        ]);
    }

    #[Route('/admin/lieu/{id}/delete', name: 'app_admin_lieu_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(int $id, Request $request, LieuRepository $lieuRepository, EntityManagerInterface $entityManager, Connection $connection): Response
    {
        $lieu = $lieuRepository->find($id);

        if ($lieu === null) {
            throw $this->createNotFoundException('Lieu introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_lieu_'.$lieu->getId(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la suppression.');

            return $this->redirectToRoute('app_admin_lieu_show', ['id' => $lieu->getId()]);
        }

        $blockers = $this->collectDeletionBlockers($lieu);
        if ($blockers !== []) {
            $this->addFlash('error', 'Suppression impossible: '.implode(', ', $blockers).'.');

            return $this->redirectToRoute('app_admin_lieu_show', ['id' => $lieu->getId()]);
        }

        // Supprime d'abord les horaires liés pour éviter les contraintes FK en base.
        $connection->delete('lieu_horaire', ['lieu_id' => (int) $lieu->getId()]);

        $entityManager->remove($lieu);
        $entityManager->flush();

        $this->addFlash('success', 'Lieu supprimé avec succès.');

        return $this->redirectToRoute('app_admin_lieu_index');
    }

    private function extractFilters(Request $request): array
    {
        $categorie = LieuCategorie::tryFrom((string) $request->query->get('categorie', ''));
        $type = LieuTypeEnum::tryFrom((string) $request->query->get('type', ''));

        return [
            'q' => trim((string) $request->query->get('q', '')),
            'categorie' => $categorie,
            'type' => $type,
            'sort' => (string) $request->query->get('sort', 'id'),
            'dir' => strtoupper((string) $request->query->get('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC',
        ];
    }

    private function handleLieuImageUpload(FormInterface $form, Lieu $lieu, SluggerInterface $slugger, ?string $existingImageUrl = null): bool
    {
        $imageFile = $form->get('imageFile')->getData();

        if ($imageFile !== null) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid('', true).'.'.$imageFile->guessExtension();

            $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/lieux';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            try {
                $imageFile->move($uploadDir, $newFilename);
                $lieu->setImageUrl('uploads/lieux/'.$newFilename);
            } catch (FileException) {
                $form->get('imageFile')->addError(new FormError('Erreur pendant l\'upload de l\'image.'));

                return false;
            }
        } elseif ($existingImageUrl !== null && trim((string) $lieu->getImageUrl()) === '') {
            $lieu->setImageUrl($existingImageUrl);
        }

        return true;
    }

    /**
     * @return string[]
     */
    private function collectDeletionBlockers(Lieu $lieu): array
    {
        $blockers = [];

        if ($lieu->getEvaluationLieu() !== null) {
            $blockers[] = 'une évaluation liée';
        }

        if (!$lieu->getLieuImages()->isEmpty()) {
            $blockers[] = 'des images associées';
        }

        if (!$lieu->getOffres()->isEmpty()) {
            $blockers[] = 'des offres associées';
        }

        if (!$lieu->getReservationOffres()->isEmpty()) {
            $blockers[] = 'des réservations associées';
        }

        if (!$lieu->getEvenements()->isEmpty()) {
            $blockers[] = 'des événements liés';
        }

        if (!$lieu->getUsers()->isEmpty()) {
            $blockers[] = 'des favoris utilisateurs';
        }

        return $blockers;
    }

    /**
     * @return array<string, array{jour:string, ouvert:bool, heure_ouverture_1:string, heure_fermeture_1:string, heure_ouverture_2:string, heure_fermeture_2:string}>
     */
    private function defaultHorairesFormData(): array
    {
        $defaults = [];
        foreach (self::WEEK_DAYS as $day) {
            $defaults[$day] = [
                'jour' => $day,
                'ouvert' => false,
                'heure_ouverture_1' => '',
                'heure_fermeture_1' => '',
                'heure_ouverture_2' => '',
                'heure_fermeture_2' => '',
            ];
        }

        return $defaults;
    }

    /**
     * @param mixed $input
     * @return array<string, array{jour:string, ouvert:bool, heure_ouverture_1:string, heure_fermeture_1:string, heure_ouverture_2:string, heure_fermeture_2:string}>
     */
    private function normalizeHorairesInputForView(mixed $input): array
    {
        $normalized = $this->defaultHorairesFormData();
        if (!is_array($input)) {
            return $normalized;
        }

        foreach (self::WEEK_DAYS as $day) {
            $row = $input[$day] ?? null;
            if (!is_array($row)) {
                continue;
            }

            $normalized[$day]['ouvert'] = isset($row['ouvert']) && (string) $row['ouvert'] !== '';
            $normalized[$day]['heure_ouverture_1'] = trim((string) ($row['heure_ouverture_1'] ?? ''));
            $normalized[$day]['heure_fermeture_1'] = trim((string) ($row['heure_fermeture_1'] ?? ''));
            $normalized[$day]['heure_ouverture_2'] = trim((string) ($row['heure_ouverture_2'] ?? ''));
            $normalized[$day]['heure_fermeture_2'] = trim((string) ($row['heure_fermeture_2'] ?? ''));
        }

        return $normalized;
    }

    /**
     * @param array<string, array{jour:string, ouvert:bool, heure_ouverture_1:string, heure_fermeture_1:string, heure_ouverture_2:string, heure_fermeture_2:string}> $horaires
     * @return array<int, array{jour:string, ouvert:bool, heure_ouverture_1:?string, heure_fermeture_1:?string, heure_ouverture_2:?string, heure_fermeture_2:?string}>|null
     */
    private function validateAndNormalizeHoraires(array $horaires, FormInterface $form): ?array
    {
        $rows = [];
        $hasError = false;

        foreach (self::WEEK_DAYS as $day) {
            $label = ucfirst($day);
            $row = $horaires[$day] ?? [
                'ouvert' => false,
                'heure_ouverture_1' => '',
                'heure_fermeture_1' => '',
                'heure_ouverture_2' => '',
                'heure_fermeture_2' => '',
            ];

            $ouvert = (bool) ($row['ouvert'] ?? false);
            $o1 = trim((string) ($row['heure_ouverture_1'] ?? ''));
            $f1 = trim((string) ($row['heure_fermeture_1'] ?? ''));
            $o2 = trim((string) ($row['heure_ouverture_2'] ?? ''));
            $f2 = trim((string) ($row['heure_fermeture_2'] ?? ''));

            if (!$ouvert) {
                $rows[] = [
                    'jour' => $day,
                    'ouvert' => false,
                    'heure_ouverture_1' => null,
                    'heure_fermeture_1' => null,
                    'heure_ouverture_2' => null,
                    'heure_fermeture_2' => null,
                ];
                continue;
            }

            if ($o1 === '' || $f1 === '') {
                $form->addError(new FormError($label.': ouverture 1 et fermeture 1 sont obligatoires.'));
                $hasError = true;
            }

            $mO1 = $this->timeToMinutes($o1);
            $mF1 = $this->timeToMinutes($f1);
            if (($o1 !== '' && $mO1 === null) || ($f1 !== '' && $mF1 === null)) {
                $form->addError(new FormError($label.': le format des heures doit être HH:MM.'));
                $hasError = true;
            }

            if ($mO1 !== null && $mF1 !== null && $mO1 >= $mF1) {
                $form->addError(new FormError($label.': l\'heure d\'ouverture 1 doit être antérieure à fermeture 1.'));
                $hasError = true;
            }

            $hasSecondSlot = ($o2 !== '' || $f2 !== '');
            if ($hasSecondSlot && ($o2 === '' || $f2 === '')) {
                $form->addError(new FormError($label.': ouverture 2 et fermeture 2 doivent être renseignées ensemble.'));
                $hasError = true;
            }

            $mO2 = $this->timeToMinutes($o2);
            $mF2 = $this->timeToMinutes($f2);
            if (($o2 !== '' && $mO2 === null) || ($f2 !== '' && $mF2 === null)) {
                $form->addError(new FormError($label.': le format des heures du deuxième créneau doit être HH:MM.'));
                $hasError = true;
            }

            if ($mO2 !== null && $mF2 !== null && $mO2 >= $mF2) {
                $form->addError(new FormError($label.': l\'heure d\'ouverture 2 doit être antérieure à fermeture 2.'));
                $hasError = true;
            }

            if ($mF1 !== null && $mO2 !== null && $mO2 <= $mF1) {
                $form->addError(new FormError($label.': le deuxième créneau doit commencer après la fermeture du premier.'));
                $hasError = true;
            }

            $rows[] = [
                'jour' => $day,
                'ouvert' => true,
                'heure_ouverture_1' => $mO1 !== null ? $o1.':00' : null,
                'heure_fermeture_1' => $mF1 !== null ? $f1.':00' : null,
                'heure_ouverture_2' => $mO2 !== null ? $o2.':00' : null,
                'heure_fermeture_2' => $mF2 !== null ? $f2.':00' : null,
            ];
        }

        return $hasError ? null : $rows;
    }

    private function timeToMinutes(string $time): ?int
    {
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return null;
        }

        [$hours, $minutes] = explode(':', $time);

        return ((int) $hours * 60) + (int) $minutes;
    }

    /**
     * @param array<int, array{jour:string, ouvert:bool, heure_ouverture_1:?string, heure_fermeture_1:?string, heure_ouverture_2:?string, heure_fermeture_2:?string}> $rows
     */
    private function saveLieuHoraires(Connection $connection, int $lieuId, array $rows): void
    {
        $connection->delete('lieu_horaire', ['lieu_id' => $lieuId]);

        foreach ($rows as $row) {
            $connection->insert('lieu_horaire', [
                'lieu_id' => $lieuId,
                'jour' => $row['jour'],
                'ouvert' => $row['ouvert'] ? 1 : 0,
                'heure_ouverture_1' => $row['heure_ouverture_1'],
                'heure_fermeture_1' => $row['heure_fermeture_1'],
                'heure_ouverture_2' => $row['heure_ouverture_2'],
                'heure_fermeture_2' => $row['heure_fermeture_2'],
            ]);
        }
    }

    /**
     * @return array<string, array{jour:string, ouvert:bool, heure_ouverture_1:string, heure_fermeture_1:string, heure_ouverture_2:string, heure_fermeture_2:string}>
     */
    private function loadHorairesFormData(Connection $connection, int $lieuId): array
    {
        $data = $this->defaultHorairesFormData();
        $rows = $connection->fetchAllAssociative(
            "SELECT jour, ouvert,
                    TIME_FORMAT(heure_ouverture_1, '%H:%i') AS heure_ouverture_1,
                    TIME_FORMAT(heure_fermeture_1, '%H:%i') AS heure_fermeture_1,
                    TIME_FORMAT(heure_ouverture_2, '%H:%i') AS heure_ouverture_2,
                    TIME_FORMAT(heure_fermeture_2, '%H:%i') AS heure_fermeture_2
             FROM lieu_horaire
             WHERE lieu_id = ?",
            [$lieuId]
        );

        foreach ($rows as $row) {
            $day = strtolower(trim((string) ($row['jour'] ?? '')));
            if (!in_array($day, self::WEEK_DAYS, true)) {
                continue;
            }

            $data[$day] = [
                'jour' => $day,
                'ouvert' => (bool) ($row['ouvert'] ?? false),
                'heure_ouverture_1' => (string) ($row['heure_ouverture_1'] ?? ''),
                'heure_fermeture_1' => (string) ($row['heure_fermeture_1'] ?? ''),
                'heure_ouverture_2' => (string) ($row['heure_ouverture_2'] ?? ''),
                'heure_fermeture_2' => (string) ($row['heure_fermeture_2'] ?? ''),
            ];
        }

        return $data;
    }
}