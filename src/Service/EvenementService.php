<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\Inscription;
use App\Entity\Ticket;
use App\Entity\Paiement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Service métier pour la gestion des événements et inscriptions.
 *
 * ⚡ IMPORTANT (évolution v2) :
 *  - Plus de validation admin requise pour s'inscrire
 *  - Statut d'inscription automatique selon l'état de paiement :
 *      • CONFIRMEE (non payé) — inscription vient d'être créée
 *      • PAYEE (payé)        — paiement réussi
 *      • REJETEE             — paiement échoué OU refusé
 *      • ANNULEE             — annulé par l'utilisateur
 *  - Les méthodes accepter/refuser sont conservées pour compatibilité
 *    mais sont désormais no-op / gérées par le statut de paiement.
 */
class EvenementService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Demander l'inscription à un événement.
     *
     * ✨ Nouvelle règle : création directe en CONFIRMEE (attente paiement).
     * Aucune validation admin n'est requise.
     *
     * @throws BadRequestHttpException Si l'utilisateur est déjà inscrit ou pas assez de places
     */
    public function demanderInscription(
        Evenement $evenement,
        User $user,
        int $nbTickets = 1
    ): Inscription {
        $repoInscription = $this->em->getRepository(Inscription::class);
        if ($repoInscription->isUserAlreadyRegistered($user->getId(), $evenement->getId())) {
            throw new BadRequestHttpException('Vous êtes déjà inscrit à cet événement.');
        }

        if (!$evenement->avoirPlacesPour($nbTickets)) {
            throw new BadRequestHttpException(
                sprintf('Il ne reste que %d place(s) disponible(s).', $evenement->getPlacesRestantes())
            );
        }

        if (!$evenement->estOuvert()) {
            throw new BadRequestHttpException('Les inscriptions à cet événement sont fermées.');
        }

        $inscription = new Inscription();
        $inscription->setEvenement($evenement);
        $inscription->setUser($user);
        $inscription->setNbTickets($nbTickets);
        // 👉 Directement CONFIRMEE, prêt pour paiement
        $inscription->setStatut(Inscription::STATUT_CONFIRMEE);
        $inscription->setPaiement(0.0);

        $this->em->persist($inscription);
        $this->em->flush();

        return $inscription;
    }

    /**
     * @deprecated Conservé pour compatibilité avec les anciennes routes admin.
     * Depuis la v2, les inscriptions sont créées directement en CONFIRMEE.
     * Cette méthode s'assure juste que le statut est cohérent.
     */
    public function accepterInscription(Inscription $inscription): Inscription
    {
        // Si déjà payée, rien à faire
        if ($inscription->getStatut() === Inscription::STATUT_PAYEE) {
            return $inscription;
        }

        $evenement = $inscription->getEvenement();
        if (!$evenement->avoirPlacesPour($inscription->getNbTickets())) {
            throw new BadRequestHttpException('Plus assez de places disponibles.');
        }

        $inscription->setStatut(Inscription::STATUT_CONFIRMEE);
        $this->em->flush();

        return $inscription;
    }

    /**
     * Refuser explicitement une inscription (admin).
     * Utile si l'admin veut bloquer un utilisateur spécifique (abus, doublon, etc.)
     */
    public function refuserInscription(Inscription $inscription, string $motif = ''): Inscription
    {
        if ($inscription->getStatut() === Inscription::STATUT_PAYEE) {
            throw new BadRequestHttpException(
                'Impossible de refuser une inscription déjà payée. Utilisez le remboursement.'
            );
        }

        $inscription->setStatut(Inscription::STATUT_REJETEE);
        $this->em->flush();

        return $inscription;
    }

    /**
     * Annulation utilisateur (ou admin).
     */
    public function annulerInscription(Inscription $inscription): Inscription
    {
        if (in_array($inscription->getStatut(), [Inscription::STATUT_ANNULEE, Inscription::STATUT_REJETEE], true)) {
            throw new BadRequestHttpException('Cette inscription est déjà annulée.');
        }

        $inscription->setStatut(Inscription::STATUT_ANNULEE);
        $this->em->flush();

        return $inscription;
    }

    /**
     * Effectuer un paiement (simulation).
     *
     * ✅ Succès → inscription passe en PAYEE + tickets générés
     * ❌ Échec  → inscription reste CONFIRMEE (l'utilisateur peut retenter)
     */
    public function effectuerPaiement(
        Inscription $inscription,
        string $methode = Paiement::METHODE_CARTE,
        bool $simulerEchec = false
    ): Paiement {
        if ($inscription->getStatut() !== Inscription::STATUT_CONFIRMEE) {
            throw new BadRequestHttpException(
                'Seules les inscriptions confirmées peuvent être payées.'
            );
        }

        if ($inscription->isPaiementEffectue()) {
            throw new BadRequestHttpException('Le paiement a déjà été effectué.');
        }

        $isSuccessful = !$simulerEchec && (rand(1, 100) > 20);

        $paiement = new Paiement();
        $paiement->setInscription($inscription);
        $paiement->setMontant($inscription->getMontantTotal());
        $paiement->setMethode($methode);
        $paiement->setReferenceCode('REF-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)));
        $paiement->setDatePaiement(new \DateTime());

        if ($isSuccessful) {
            $paiement->setStatut(Paiement::STATUT_PAYE);
            $inscription->setStatut(Inscription::STATUT_PAYEE);
            $this->genererTickets($inscription);
        } else {
            $paiement->setStatut(Paiement::STATUT_ECHOUE);
            // L'inscription reste CONFIRMEE → retry possible
        }

        $this->em->persist($paiement);
        $this->em->flush();

        return $paiement;
    }

    /**
     * Générer les tickets après paiement réussi.
     */
    public function genererTickets(Inscription $inscription): void
    {
        if (!$inscription->getTickets()->isEmpty()) {
            return;
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
     * Rembourser un paiement.
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
     * @deprecated Depuis la v2, plus aucune inscription n'est en EN_ATTENTE.
     * Conservé pour compatibilité.
     */
    public function getInscriptionsEnAttente(Evenement $evenement = null): array
    {
        $repo = $this->em->getRepository(Inscription::class);
        return $repo->findInscriptionsEnAttente($evenement);
    }

    /**
     * Statistiques enrichies d'un événement.
     * Inclut désormais le statut dérivé (payé / non payé / rejeté).
     */
    public function getStatistiquesEvenement(Evenement $evenement): array
    {
        $inscriptions = $evenement->getInscriptions();

        return [
            'total_demandes' => $inscriptions->count(),
            'confirmees'    => $inscriptions->filter(fn(Inscription $i) => $i->getStatut() === Inscription::STATUT_CONFIRMEE)->count(),
            'payees'        => $inscriptions->filter(fn(Inscription $i) => $i->getStatut() === Inscription::STATUT_PAYEE)->count(),
            'en_attente'    => $inscriptions->filter(fn(Inscription $i) => $i->getStatut() === Inscription::STATUT_EN_ATTENTE)->count(),
            'rejetees'      => $inscriptions->filter(fn(Inscription $i) => $i->getStatut() === Inscription::STATUT_REJETEE)->count(),
            'annulees'      => $inscriptions->filter(fn(Inscription $i) => $i->getStatut() === Inscription::STATUT_ANNULEE)->count(),
            // Vue métier "payé / non-payé" (plus parlante côté admin)
            'non_payees'    => $inscriptions->filter(
                fn(Inscription $i) => $i->getStatut() === Inscription::STATUT_CONFIRMEE && !$i->isPaiementEffectue()
            )->count(),
            'places_restantes'  => $evenement->getPlacesRestantes(),
            'taux_remplissage'  => $evenement->getTauxRemplissage(),
        ];
    }
}
