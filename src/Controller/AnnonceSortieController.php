<?php

namespace App\Controller;

use App\Entity\AnnonceSortie;
use App\Entity\User;
use App\Form\AnnonceSortieType;
use App\Repository\AnnonceSortieRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class AnnonceSortieController extends AbstractController
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    #[Route('/sorties/ajouter', name: 'app_sorties_create')]
    public function createFront(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        SluggerInterface $slugger
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_sorties_create');
        }

        $sortie = new AnnonceSortie();
        $sortie->setStatut('OUVERTE');

        $form = $this->createForm(AnnonceSortieType::class, $sortie, [
            'is_admin' => false,
            'require_image' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->resolveCurrentUser($userRepository);
            if ($user === null) {
                $this->addFlash('error', 'Aucun utilisateur disponible pour attacher cette annonce.');
                return $this->redirectToRoute('app_sorties');
            }

            $sortie->setUser($user);
            $this->handleImageUpload($form->get('imageFile')->getData(), $sortie, $slugger, $request);

            $entityManager->persist($sortie);
            $entityManager->flush();

            $this->addFlash('success', 'Annonce creee avec succes.');
            return $this->redirectToRoute('app_sorties');
        }

        return $this->render('front/sortie/create.html.twig', [
            'active' => 'sorties',
            'form' => $form->createView(),
        ]);
    }

    #[Route('/sorties/{id}/modifier', name: 'app_sorties_edit', requirements: ['id' => '\\d+'])]
    public function editFront(
        int $id,
        Request $request,
        AnnonceSortieRepository $sortieRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_sorties_edit', ['id' => $id]);
        }

        $sortie = $sortieRepository->find($id);
        if (!$sortie) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        if (!$this->canManageSortie($sortie)) {
            $this->addFlash('error', 'Seul le createur ou un administrateur peut modifier cette annonce.');
            return $this->redirectToRoute('app_sorties');
        }

        $form = $this->createForm(AnnonceSortieType::class, $sortie, [
            'is_admin' => false,
            'require_image' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form->get('imageFile')->getData(), $sortie, $slugger, $request);
            $entityManager->flush();

            $this->addFlash('success', 'Annonce modifiee avec succes.');
            return $this->redirectToRoute('app_sorties');
        }

        return $this->render('front/sortie/edit.html.twig', [
            'active' => 'sorties',
            'sortie' => $sortie,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/sorties/{id}/supprimer', name: 'app_sorties_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function deleteFront(
        int $id,
        Request $request,
        AnnonceSortieRepository $sortieRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_sorties');
        }

        $sortie = $sortieRepository->find($id);
        if (!$sortie) {
            $this->addFlash('error', 'Annonce introuvable.');
            return $this->redirectToRoute('app_sorties');
        }

        if (!$this->canManageSortie($sortie)) {
            $this->addFlash('error', 'Seul le createur ou un administrateur peut supprimer cette annonce.');
            return $this->redirectToRoute('app_sorties');
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('sortie_delete_front_'.$sortie->getId(), $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la suppression.');
            return $this->redirectToRoute('app_sorties');
        }

        try {
            $entityManager->remove($sortie);
            $entityManager->flush();
            $this->addFlash('success', 'Annonce supprimee avec succes.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Suppression impossible: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_sorties');
    }

    #[Route('/admin/sorties/ajouter', name: 'app_admin_sorties_create')]
    public function createAdmin(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        SluggerInterface $slugger
    ): Response {
        $sortie = new AnnonceSortie();
        $sortie->setStatut('OUVERTE');

        $form = $this->createForm(AnnonceSortieType::class, $sortie, [
            'is_admin' => true,
            'require_image' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->resolveCurrentUser($userRepository);
            if ($user === null) {
                $this->addFlash('error', 'Aucun utilisateur disponible pour attacher cette annonce.');
                return $this->redirectToRoute('app_admin_sorties');
            }

            $sortie->setUser($user);
            $this->handleImageUpload($form->get('imageFile')->getData(), $sortie, $slugger, $request);

            $entityManager->persist($sortie);
            $entityManager->flush();

            $this->addFlash('success', 'Annonce creee avec succes.');
            return $this->redirectToRoute('app_admin_sorties');
        }

        return $this->render('admin/sortie/create.html.twig', [
            'active' => 'sorties',
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/sorties/{id}/modifier', name: 'app_admin_sorties_edit', requirements: ['id' => '\\d+'])]
    public function editAdmin(
        int $id,
        Request $request,
        AnnonceSortieRepository $sortieRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $sortie = $sortieRepository->find($id);
        if (!$sortie) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        if (!$this->canManageSortie($sortie)) {
            $this->addFlash('error', 'Seul le createur ou un administrateur peut modifier cette annonce.');
            return $this->redirectToRoute('app_admin_sorties');
        }

        $form = $this->createForm(AnnonceSortieType::class, $sortie, [
            'is_admin' => true,
            'require_image' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form->get('imageFile')->getData(), $sortie, $slugger, $request);
            $entityManager->flush();

            $this->addFlash('success', 'Annonce modifiee avec succes.');
            return $this->redirectToRoute('app_admin_sorties');
        }

        return $this->render('admin/sortie/edit.html.twig', [
            'active' => 'sorties',
            'sortie' => $sortie,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/sorties/{id}/supprimer', name: 'app_admin_sorties_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function deleteAdmin(
        int $id,
        Request $request,
        AnnonceSortieRepository $sortieRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $sortie = $sortieRepository->find($id);
        if (!$sortie) {
            $this->addFlash('error', 'Annonce introuvable.');
            return $this->redirectToRoute('app_admin_sorties');
        }

        if (!$this->canManageSortie($sortie)) {
            $this->addFlash('error', 'Seul le createur ou un administrateur peut supprimer cette annonce.');
            return $this->redirectToRoute('app_admin_sorties');
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('sortie_delete_admin_'.$sortie->getId(), $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la suppression.');
            return $this->redirectToRoute('app_admin_sorties');
        }

        try {
            $entityManager->remove($sortie);
            $entityManager->flush();
            $this->addFlash('success', 'Annonce supprimee avec succes.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Suppression impossible: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_sorties');
    }

    #[Route('/admin/sorties/{id}/terminer', name: 'app_admin_sorties_mark_terminee', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function markTermineeAdmin(
        int $id,
        Request $request,
        AnnonceSortieRepository $sortieRepository,
        EntityManagerInterface $entityManager,
        Connection $connection,
        NotificationService $notificationService
    ): Response {
        $sortie = $sortieRepository->find($id);
        if (!$sortie) {
            $this->addFlash('error', 'Annonce introuvable.');
            return $this->redirectToRoute('app_admin_sorties');
        }

        if (!$this->canManageSortie($sortie)) {
            $this->addFlash('error', 'Seul le createur ou un administrateur peut terminer cette annonce.');
            return $this->redirectToRoute('app_admin_sorties');
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('sortie_terminee_admin_'.$sortie->getId(), $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide pour le changement de statut.');
            return $this->redirectToRoute('app_admin_sorties');
        }

        if ($sortie->getStatut() === 'TERMINEE') {
            $this->addFlash('info', 'Cette annonce est deja marquee TERMINEE.');
            return $this->redirectToRoute('app_admin_sorties');
        }

        $sortie->setStatut('TERMINEE');
        $entityManager->flush();

        $recipientIds = array_map(
            static fn (array $row): int => (int) $row['user_id'],
            $connection->fetchAllAssociative(
                "SELECT DISTINCT user_id FROM participation_annonce WHERE annonce_id = ? AND statut = 'CONFIRMEE'",
                [$id]
            )
        );

        $sender = $this->getUser();
        $senderId = $sender instanceof User ? (int) $sender->getId() : null;

        $notificationService->notifyMany(
            $recipientIds,
            $senderId,
            'SORTIE_TERMINATED',
            'Sortie marquée terminée',
            'La sortie "'.$sortie->getTitre().'" a ete marquee TERMINEE par l\'organisateur.',
            'annonce_sortie',
            (int) $sortie->getId(),
            ['statut' => 'TERMINEE']
        );

        $this->addFlash('success', 'Annonce marquee TERMINEE avec succes.');
        return $this->redirectToRoute('app_admin_sorties');
    }

    private function resolveCurrentUser(UserRepository $userRepository): ?User
    {
        $connected = $this->getUser();
        if ($connected instanceof User) {
            return $connected;
        }

        return $userRepository->findOneBy([], ['id' => 'ASC']);
    }

    private function handleImageUpload(?UploadedFile $uploadedFile, AnnonceSortie $sortie, SluggerInterface $slugger, ?Request $request = null): void
    {
        if (!$uploadedFile) {
            return;
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $uploadDir = $projectDir.'/public/uploads/sorties';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $originalName = pathinfo((string) $uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $slugger->slug($originalName ?: 'sortie-image');
        $extension = $uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension() ?: 'bin';
        $newName = $safeName.'-'.uniqid('', true).'.'.$extension;

        $uploadedFile->move($uploadDir, $newName);
        $publicPath = '/uploads/sorties/'.$newName;

        $activeRequest = $request ?? $this->requestStack->getCurrentRequest();
        if ($activeRequest !== null) {
            $sortie->setImage_url(rtrim($activeRequest->getSchemeAndHttpHost(), '/').$publicPath);
            return;
        }

        $sortie->setImage_url($publicPath);
    }

    private function canManageSortie(AnnonceSortie $sortie): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return false;
        }

        $owner = $sortie->getUser();
        if (!$owner instanceof User) {
            return false;
        }

        return $owner->getId() === $currentUser->getId();
    }
}
