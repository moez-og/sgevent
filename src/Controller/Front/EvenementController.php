<?php

namespace App\Controller\Front;

use App\Entity\Evenement;
use App\Entity\Inscription;
use App\Entity\Paiement;
use App\Repository\EvenementRepository;
use App\Repository\InscriptionRepository;
use App\Service\AiEventService;
use App\Service\EvenementService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/evenements')]
class EvenementController extends AbstractController
{
    public function __construct(
        private EvenementService $evenementService,
        private InscriptionRepository $inscriptionRepository
    ) {}

    #[Route('', name: 'app_evenements', methods: ['GET'])]
    public function index(Request $request, EvenementRepository $repository): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'type' => (string) $request->query->get('type', ''),
            'prix' => (string) $request->query->get('prix', ''),
        ];

        $evenements = $repository->findUpcomingWithFilters(
            $filters['q'],
            $filters['type'],
            $filters['prix']
        );

        return $this->render('front/evenement/index.html.twig', [
            'active' => 'evenements',
            'events' => $evenements,
            'filters' => $filters,
        ]);
    }

    #[Route('/ai-recommend', name: 'app_evenements_ai_recommend', methods: ['GET'])]
    public function aiRecommend(Request $request, EvenementRepository $repository, AiEventService $aiService): Response
    {
        $userInterests = json_decode((string) $request->query->get('interests', '{}'), true) ?? [];
        $events = $repository->findUpcoming();
        $result = $aiService->recommendEvents($events, $userInterests);

        $eventsById = [];
        foreach ($events as $e) {
            $eventsById[$e->getId()] = $e;
        }

        $recommendations = [];
        foreach ($result['recommendations'] ?? [] as $rec) {
            $id = (int) ($rec['id'] ?? 0);
            if (!isset($eventsById[$id])) {
                continue;
            }
            $e = $eventsById[$id];
            $recommendations[] = [
                'id'              => $e->getId(),
                'titre'           => $e->getTitre(),
                'score'           => (int) ($rec['score'] ?? 0),
                'reason'          => (string) ($rec['reason'] ?? ''),
                'prix'            => $e->getPrix(),
                'date'            => $e->getDateDebut()?->format('d/m/Y H:i'),
                'lieu'            => $e->getLieu()?->getVille() ?? $e->getLieu()?->getNom() ?? '',
                'type'            => $e->getType(),
                'statut'          => $e->getStatut(),
                'image'           => (string) ($e->getImageUrl() ?? ''),
                'places_restantes'=> $e->getPlacesRestantes(),
                'capacite'        => $e->getCapaciteMax(),
            ];
        }

        return $this->json(['recommendations' => $recommendations]);
    }

    #[Route('/{id<\\d+>}', name: 'app_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        $user = $this->getUser();
        $inscription = null;

        // Vérifier si l'utilisateur est déjà inscrit
        if ($user) {
            $inscription = $this->inscriptionRepository->findOneBy([
                'user' => $user,
                'evenement' => $evenement,
            ]);
        }

        return $this->render('front/evenement/show.html.twig', [
            'active' => 'evenements',
            'event' => $evenement,
            'inscription' => $inscription,
            'mon_inscription' => $inscription ? $inscription->getStatut() : null,
        ]);
    }

    /**
     * Afficher le formulaire d'inscription (choix du nombre de tickets)
     */
    #[Route('/{id<\\d+>}/inscrire', name: 'app_evenement_inscrire_form', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function inscrireForm(int $id, EvenementRepository $repository): Response
    {
        $evenement = $repository->find($id);
        if (!$evenement) {
            $this->addFlash('error', 'Événement introuvable.');
            return $this->redirectToRoute('app_evenements');
        }
        // Vérifier que l'événement est ouvert
        if (!$evenement->estOuvert()) {
            $this->addFlash('error', 'Les inscriptions à cet événement sont fermées.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $placesRestantes = $evenement->getPlacesRestantes();

        return $this->render('front/evenement/inscrire.html.twig', [
            'active' => 'evenements',
            'event' => $evenement,
            'places_restantes' => $placesRestantes,
            'max_tickets' => min(5, $placesRestantes), // Max 5 tickets par inscription
        ]);
    }

    /**
     * Soumettre l'inscription (POST)
     */
    #[Route('/{id<\\d+>}/inscrire', name: 'app_evenement_inscrire', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function inscrire(int $id, Request $request, EvenementRepository $repository): Response
    {
        $evenement = $repository->find($id);
        if (!$evenement) {
            $this->addFlash('error', 'Événement introuvable.');
            return $this->redirectToRoute('app_evenements');
        }

        $user = $this->getUser();
        $nbTickets = (int) $request->request->get('nb_tickets', 1);

        // Validation : vérifier que le nombre de tickets est valide
        if ($nbTickets < 1 || $nbTickets > 5) {
            $this->addFlash('error', 'Nombre de tickets invalide (1-5).');
            return $this->redirectToRoute('app_evenement_inscrire_form', ['id' => $evenement->getId()]);
        }

        try {
            // ✨ Créer inscription EN_ATTENTE (validation admin requise)
            $inscription = $this->evenementService->demanderInscription($evenement, $user, $nbTickets);
            
            $this->addFlash('success', sprintf(
                '✅ Inscription réussie ! Vous avez demandé %d ticket(s). En attente de validation de l\'administrateur.',
                $nbTickets
            ));
            
            return $this->redirectToRoute('app_mon_profil_inscriptions');
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ ' . $e->getMessage());
            return $this->redirectToRoute('app_evenement_inscrire_form', ['id' => $evenement->getId()]);
        }
    }

    /**
     * Effectuer le paiement (pour une inscription confirmée)
     */
    #[Route('/inscription/{id}/paiement', name: 'app_inscription_paiement', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function effectuerPaiement(Inscription $inscription, Request $request): Response
    {
        // Vérifier que c'est bien l'inscription de l'utilisateur
        if ($inscription->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas payer l\'inscription d\'une autre personne.');
        }

        // Vérifier que l'inscription est confirmée et en attente de paiement
        if ($inscription->getStatut() !== Inscription::STATUT_CONFIRMEE) {
            $this->addFlash('error', 'Cette inscription n\'est pas en attente de paiement.');
            return $this->redirectToRoute('app_mon_profil_inscriptions');
        }

        if ($request->isMethod('GET')) {
            // Afficher le formulaire de paiement
            $montant = $inscription->getMontantTotal();
            
            return $this->render('front/evenement/paiement.html.twig', [
                'active' => 'evenements',
                'inscription' => $inscription,
                'montant' => $montant,
                'methodes' => [
                    Paiement::METHODE_CARTE => 'Carte bancaire',
                    Paiement::METHODE_CASH => 'Espèces',
                    Paiement::METHODE_WALLET => 'Portefeuille numérique',
                ],
            ]);
        }

        // POST : soumettre le paiement
        $methode = $request->request->get('methode', Paiement::METHODE_CARTE);
        $nomCarte = trim((string) $request->request->get('nom_carte', ''));
        $quatreDerniers = trim((string) $request->request->get('quatre_derniers', ''));

        if (!in_array($methode, Paiement::METHODES_VALIDES)) {
            $this->addFlash('error', 'Méthode de paiement invalide.');
            return $this->redirectToRoute('app_inscription_paiement', ['id' => $inscription->getId()]);
        }

        if ($methode === Paiement::METHODE_CARTE) {
            if ($nomCarte === '') {
                $this->addFlash('error', 'Le nom sur la carte est obligatoire.');
                return $this->redirectToRoute('app_inscription_paiement', ['id' => $inscription->getId()]);
            }
            if (!preg_match('/^\d{4}$/', $quatreDerniers)) {
                $this->addFlash('error', 'Les 4 derniers chiffres doivent contenir exactement 4 chiffres.');
                return $this->redirectToRoute('app_inscription_paiement', ['id' => $inscription->getId()]);
            }
        }

        try {
            $paiement = $this->evenementService->effectuerPaiement($inscription, $methode);

            if ($paiement->estReussi()) {
                $session = $request->getSession();
                if ($session) {
                    $session->getFlashBag()->set('error', []);
                }
                $this->addFlash('success', sprintf(
                    '✅ Paiement réussi ! Référence: %s. Vos tickets ont été générés.',
                    $paiement->getReferenceCode()
                ));
                return $this->redirectToRoute('app_inscription_tickets', ['id' => $inscription->getId()]);
            } else {
                $this->addFlash('error', '❌ Paiement échoué. Veuillez réessayer avec une autre méthode.');
                return $this->redirectToRoute('app_inscription_paiement', ['id' => $inscription->getId()]);
            }
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ ' . $e->getMessage());
            return $this->redirectToRoute('app_inscription_paiement', ['id' => $inscription->getId()]);
        }
    }

    /**
     * Afficher les tickets générés
     */
    #[Route('/inscription/{id}/tickets', name: 'app_inscription_tickets', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function voirTickets(Inscription $inscription): Response
    {
        // Vérifier que c'est bien l'inscription de l'utilisateur
        if ($inscription->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir les tickets d\'une autre personne.');
        }

        if ($inscription->getStatut() !== Inscription::STATUT_PAYEE) {
            $this->addFlash('error', 'Seules les inscriptions payées ont des tickets.');
            return $this->redirectToRoute('app_mon_profil_inscriptions');
        }

        return $this->render('front/evenement/tickets.html.twig', [
            'active' => 'evenements',
            'inscription' => $inscription,
            'tickets' => $inscription->getTickets(),
        ]);
    }

    /**
     * Télécharger les tickets au format PDF
     */
    #[Route('/inscription/{id<\\d+>}/tickets/pdf', name: 'app_inscription_tickets_pdf', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function telechargerTicketsPdf(Inscription $inscription): Response
    {
        if ($inscription->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès non autorisé.');
        }

        if ($inscription->getStatut() !== Inscription::STATUT_PAYEE) {
            $this->addFlash('error', 'Le PDF est disponible uniquement après paiement.');
            return $this->redirectToRoute('app_mon_profil_inscriptions');
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $html = $this->renderView('front/evenement/tickets-pdf.html.twig', [
            'inscription' => $inscription,
            'tickets' => $inscription->getTickets(),
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'tickets-'.$inscription->getId().'.pdf';

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Afficher mes inscriptions
     */
    #[Route('/mes-inscriptions', name: 'app_mon_profil_inscriptions', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function mesInscriptions(Request $request, InscriptionRepository $repo): Response
    {
        $session = $request->getSession();
        if ($session && $session->getFlashBag()->has('success')) {
            $session->getFlashBag()->set('error', []);
        }

        $user = $this->getUser();
        $inscriptions = $repo->findBy(['user' => $user], ['dateCreation' => 'DESC']);

        // Organiser par statut
        $parStatut = [];
        foreach ([
            Inscription::STATUT_EN_ATTENTE => '⏳ En attente de validation',
            Inscription::STATUT_CONFIRMEE => '✔️ Confirmée (en attente de paiement)',
            Inscription::STATUT_PAYEE => '✅ Payée',
            Inscription::STATUT_REJETEE => '❌ Refusée',
            Inscription::STATUT_ANNULEE => '🗑️ Annulée',
        ] as $statut => $label) {
            $parStatut[$label] = array_filter($inscriptions, fn(Inscription $i) => $i->getStatut() === $statut);
        }

        return $this->render('front/evenement/mes-inscriptions.html.twig', [
            'active' => 'evenements',
            'inscriptions_par_statut' => $parStatut,
        ]);
    }

    /**
     * Annuler mon inscription
     */
    #[Route('/inscription/{id}/annuler', name: 'app_inscription_annuler', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function annulerInscription(Inscription $inscription, Request $request): Response
    {
        if ($inscription->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('annuler' . $inscription->getId(), $request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
        } else {
            try {
                if ($inscription->getStatut() === Inscription::STATUT_PAYEE) {
                    // Rembourser si payée
                    $this->evenementService->rembourserInscription($inscription);
                    $this->addFlash('info', '💰 Inscription annulée et remboursée.');
                } else {
                    // Juste annuler
                    $this->evenementService->annulerInscription($inscription);
                    $this->addFlash('info', '🗑️ Inscription annulée.');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_mon_profil_inscriptions');
    }
}