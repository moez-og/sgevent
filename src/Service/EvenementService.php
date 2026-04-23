<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\Inscription;
use App\Entity\Ticket;
use App\Entity\Paiement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class EvenementService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Demander l'inscription à un événement (crée une inscription EN_ATTENTE)
     * 
     * @throws BadRequestHttpException Si l'utilisateur est déjà inscrit ou pas assez de places
     */
    public function demanderInscription(
        Evenement $evenement,
        User $user,
        int $nbTickets = 1
    ): Inscription {
        // Règle 1 : Vérifier si déjà inscrit (non annulée/rejetée)
        $repoInscription = $this->em->getRepository(Inscription::class);
        if ($repoInscription->isUserAlreadyRegistered($user->getId(), $evenement->getId())) {
            throw new BadRequestHttpException('Vous êtes déjà inscrit à cet événement.');
        }

        // Règle 2 : Vérifier la capacité restante
        if (!$evenement->avoirPlacesPour($nbTickets)) {
            throw new BadRequestHttpException(
                sprintf('Il ne reste que %d place(s) disponible(s).', $evenement->getPlacesRestantes())
            );
        }

        // Règle 3 : Vérifier que l'événement est ouvert
        if (!$evenement->estOuvert()) {
            throw new BadRequestHttpException('Les inscriptions à cet événement sont fermées.');
        }

        // Créer l'inscription
        $inscription = new Inscription();
        $inscription->setEvenement($evenement);
        $inscription->setUser($user);
        $inscription->setNbTickets($nbTickets);
        $inscription->setStatut(Inscription::STATUT_EN_ATTENTE);  // ✨ EN_ATTENTE pour validation admin
        $inscription->setPaiement(0.0);  // Pas de paiement à ce stade

        $this->em->persist($inscription);
        $this->em->flush();

        return $inscription;
    }

    /**
     * Accepter une inscription (admin valide)
     * Passe de EN_ATTENTE → CONFIRMEE
     */
    public function accepterInscription(Inscription $inscription): Inscription
    {
        if (!$inscription->peutEtreConfirmee()) {
            throw new BadRequestHttpException(
                sprintf('Impossible de confirmer une inscription au statut %s', $inscription->getStatut())
            );
        }

        // Vérifier encore la capacité (elle peut avoir changé)
        $evenement = $inscription->getEvenement();
        if (!$evenement->avoirPlacesPour($inscription->getNbTickets())) {
            throw new BadRequestHttpException('Plus assez de places disponibles.');
        }

        $inscription->setStatut(Inscription::STATUT_CONFIRMEE);
        $this->em->flush();

        return $inscription;
    }

    /**
     * Refuser une inscription (admin valide)
     * Passe de EN_ATTENTE → REJETEE
     */
    public function refuserInscription(Inscription $inscription, string $motif = ''): Inscription
    {
        if ($inscription->getStatut() !== Inscription::STATUT_EN_ATTENTE) {
            throw new BadRequestHttpException('Impossible de refuser une inscription non EN_ATTENTE.');
        }

        $inscription->setStatut(Inscription::STATUT_REJETEE);
        $this->em->flush();

        return $inscription;
    }

    /**
     * Annuler une inscription (utilisateur ou admin)
     */
    public function annulerInscription(Inscription $inscription): Inscription
    {
        if (in_array($inscription->getStatut(), [Inscription::STATUT_ANNULEE, Inscription::STATUT_REJETEE])) {
            throw new BadRequestHttpException('Cette inscription est déjà annulée.');
        }

        $inscription->setStatut(Inscription::STATUT_ANNULEE);
        $this->em->flush();

        return $inscription;
    }

    /**
     * Effectuer un paiement (simulation simple)
     * Passe de CONFIRMEE → PAYEE
     * 
     * @param string $methode CARTE, CASH, WALLET
     * @param bool $simulerEchec Si true, simule 20% d'échec
     */
    public function effectuerPaiement(
        Inscription $inscription,
        string $methode = Paiement::METHODE_CARTE,
        bool $simulerEchec = false
    ): Paiement {
        // Vérifier que l'inscription est confirmée et paiement en attente
        if ($inscription->getStatut() !== Inscription::STATUT_CONFIRMEE) {
            throw new BadRequestHttpException(
                'Seules les inscriptions confirmées peuvent être payées.'
            );
        }

        if ($inscription->isPaiementEffectue()) {
            throw new BadRequestHttpException('Le paiement a déjà été effectué.');
        }

        // Simulation du paiement : 80% succès, 20% échec
        $isSuccessful = !$simulerEchec && (rand(1, 100) > 20);

        // Créer l'enregistrement de paiement
        $paiement = new Paiement();
        $paiement->setInscription($inscription);
        $paiement->setMontant($inscription->getMontantTotal());
        $paiement->setMethode($methode);
        $paiement->setReferenceCode('REF-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)));
        $paiement->setDatePaiement(new \DateTime());

        if ($isSuccessful) {
            $paiement->setStatut(Paiement::STATUT_PAYE);
            $inscription->setStatut(Inscription::STATUT_PAYEE);
            
            // Générer les tickets
            $this->genererTickets($inscription);
        } else {
            $paiement->setStatut(Paiement::STATUT_ECHOUE);
        }

        $this->em->persist($paiement);
        $this->em->flush();

        return $paiement;
    }

    /**
     * Générer les tickets pour une inscription payée
     */
    public function genererTickets(Inscription $inscription): void
    {
        // Vérifier si les tickets existent déjà
        if (!$inscription->getTickets()->isEmpty()) {
            return; // Tickets déjà générés
        }

        $nbTickets = $inscription->getNbTickets();
        for ($i = 0; $i < $nbTickets; $i++) {
            $ticket = new Ticket();
            $ticket->setInscription($inscription);
            $ticket->setDate(new \DateTime());
            $this->em->persist($ticket);
        }

        $this->em->flush();
    }

    /**
     * Rembourser un paiement
     */
    public function rembourserInscription(Inscription $inscription, string $motif = ''): Paiement
    {
        $paiement = $inscription->getPaiementPrincipal();

        if (!$paiement || !$paiement->estReussi()) {
            throw new BadRequestHttpException('Aucun paiement à rembourser.');
        }

        $paiement->setStatut(Paiement::STATUT_REMBOURSE);
        $inscription->setStatut(Inscription::STATUT_ANNULEE);

        $this->em->flush();

        return $paiement;
    }

    /**
     * Obtient les inscriptions en attente de validation
     */
    public function getInscriptionsEnAttente(Evenement $evenement = null): array
    {
        $repo = $this->em->getRepository(Inscription::class);
        return $repo->findInscriptionsEnAttente($evenement);
    }

    /**
     * Obtient les statistiques d'un événement
     */
    public function getStatistiquesEvenement(Evenement $evenement): array
    {
        $inscriptions = $evenement->getInscriptions();
        
        return [
            'total_demandes' => $inscriptions->count(),
            'confirmees' => $inscriptions->filter(fn(Inscription $i) => $i->getStatut() === Inscription::STATUT_CONFIRMEE)->count(),
            'payees' => $inscriptions->filter(fn(Inscription $i) => $i->getStatut() === Inscription::STATUT_PAYEE)->count(),
            'en_attente' => $inscriptions->filter(fn(Inscription $i) => $i->getStatut() === Inscription::STATUT_EN_ATTENTE)->count(),
            'rejetees' => $inscriptions->filter(fn(Inscription $i) => $i->getStatut() === Inscription::STATUT_REJETEE)->count(),
            'annulees' => $inscriptions->filter(fn(Inscription $i) => $i->getStatut() === Inscription::STATUT_ANNULEE)->count(),
            'places_restantes' => $evenement->getPlacesRestantes(),
            'taux_remplissage' => $evenement->getTauxRemplissage(),
        ];
    }
}