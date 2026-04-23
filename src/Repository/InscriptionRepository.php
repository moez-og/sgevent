<?php

namespace App\Repository;

use App\Entity\Evenement;
use App\Entity\Inscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inscription::class);
    }

    // Vérifie si un utilisateur est déjà inscrit à un événement
    public function isUserAlreadyRegistered(int $userId, int $evenementId): bool
    {
        return (bool) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.user = :userId')
            ->andWhere('i.evenement = :evenementId')
            ->andWhere('i.statut NOT IN (:invalid)')
            ->setParameter('userId', $userId)
            ->setParameter('evenementId', $evenementId)
            ->setParameter('invalid', ['ANNULEE', 'REJETEE'])
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Trouve les inscriptions en attente de validation (statut EN_ATTENTE)
     */
    public function findInscriptionsEnAttente(?Evenement $evenement = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.statut = :en_attente')
            ->setParameter('en_attente', 'EN_ATTENTE')
            ->orderBy('i.dateCreation', 'ASC');

        if ($evenement) {
            $qb->andWhere('i.evenement = :evenement')
                ->setParameter('evenement', $evenement);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les inscriptions en attente de paiement (CONFIRMEE sans paiement)
     */
    public function findInscriptionsEnAttentePaiement(?Evenement $evenement = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.paiements', 'p')
            ->where('i.statut = :confirmee')
            ->andWhere('p.id IS NULL OR p.statut != :paye')
            ->setParameter('confirmee', 'CONFIRMEE')
            ->setParameter('paye', 'PAYE')
            ->orderBy('i.dateCreation', 'ASC');

        if ($evenement) {
            $qb->andWhere('i.evenement = :evenement')
                ->setParameter('evenement', $evenement);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les inscriptions par statut pour un événement
     */
    public function countByStatut(Evenement $evenement, string $statut): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.evenement = :evenement')
            ->andWhere('i.statut = :statut')
            ->setParameter('evenement', $evenement)
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Obtient les tickets réservés pour un événement
     */
    public function countTicketsReserves(Evenement $evenement): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('SUM(i.nbTickets)')
            ->where('i.evenement = :evenement')
            ->andWhere('i.statut IN (:statuts)')
            ->setParameter('evenement', $evenement)
            ->setParameter('statuts', ['CONFIRMEE', 'PAYEE'])
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }
}