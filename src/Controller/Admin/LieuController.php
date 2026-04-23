<?php

namespace App\Controller\Admin;

use App\Entity\Lieu;
use App\Form\LieuFormType;
use App\Repository\LieuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/lieux', name: 'app_admin_lieux')]
class LieuController extends AbstractController
{
    // -------------------------------------------------------------------------
    // LIST + SEARCH
    // -------------------------------------------------------------------------

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request, LieuRepository $repo): Response
    {
        $search    = trim((string) $request->query->get('q', ''));
        $categorie = $request->query->get('categorie', '');
        $type      = $request->query->get('type', '');

        if ($search !== '') {
            $lieux = $repo->search($search);
        } elseif ($categorie !== '') {
            $lieux = $repo->findByCategorie($categorie);
        } elseif ($type !== '') {
            $lieux = $repo->findByType($type);
        } else {
            $lieux = $repo->findAllOrderedByName();
        }

        return $this->render('admin/lieu/index.html.twig', [
            'active'    => 'lieux',
            'lieux'     => $lieux,
            'search'    => $search,
            'categorie' => $categorie,
            'type'      => $type,
            'categories' => Lieu::CATEGORIES,
            'types'      => Lieu::TYPES,
        ]);
    }

    // -------------------------------------------------------------------------
    // SHOW
    // -------------------------------------------------------------------------

    #[Route('/{id}', name: '_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Lieu $lieu): Response
    {
        return $this->render('admin/lieu/show.html.twig', [
            'active' => 'lieux',
            'lieu'   => $lieu,
        ]);
    }

    // -------------------------------------------------------------------------
    // CREATE
    // -------------------------------------------------------------------------

    #[Route('/nouveau', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $lieu = new Lieu();
        $form = $this->createForm(LieuFormType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($lieu);
            $em->flush();

            $this->addFlash('success', sprintf('Le lieu « %s » a été créé avec succès.', $lieu->getNom()));

            return $this->redirectToRoute('app_admin_lieux');
        }

        return $this->render('admin/lieu/new.html.twig', [
            'active' => 'lieux',
            'lieu'   => $lieu,
            'form'   => $form,
        ]);
    }

    // -------------------------------------------------------------------------
    // EDIT
    // -------------------------------------------------------------------------

    #[Route('/{id}/modifier', name: '_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Lieu $lieu, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(LieuFormType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', sprintf('Le lieu « %s » a été modifié avec succès.', $lieu->getNom()));

            return $this->redirectToRoute('app_admin_lieux_show', ['id' => $lieu->getId()]);
        }

        return $this->render('admin/lieu/edit.html.twig', [
            'active' => 'lieux',
            'lieu'   => $lieu,
            'form'   => $form,
        ]);
    }

    // -------------------------------------------------------------------------
    // DELETE
    // -------------------------------------------------------------------------

    #[Route('/{id}/supprimer', name: '_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Lieu $lieu, EntityManagerInterface $em): Response
    {
        // Protection CSRF
        if ($this->isCsrfTokenValid('delete_lieu_' . $lieu->getId(), (string) $request->request->get('_token'))) {
            $nomLieu = $lieu->getNom();
            $em->remove($lieu);
            $em->flush();

            $this->addFlash('success', sprintf('Le lieu « %s » a été supprimé.', $nomLieu));
        } else {
            $this->addFlash('danger', 'Token CSRF invalide. Suppression annulée.');
        }

        return $this->redirectToRoute('app_admin_lieux');
    }
}
