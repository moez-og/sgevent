<?php

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    public function findUpcoming(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.dateDebut > :now')
            ->andWhere('e.statut = :ouvert')
            ->setParameter('now', new \DateTime())
            ->setParameter('ouvert', 'OUVERT')
            ->orderBy('e.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findUpcomingWithFilters(?string $query, ?string $type, ?string $prix): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.lieu', 'l')
            ->addSelect('l')
            ->where('e.dateDebut > :now')
            ->andWhere('e.statut = :ouvert')
            ->setParameter('now', new \DateTime())
            ->setParameter('ouvert', 'OUVERT');

        $query = $query ? trim($query) : '';
        if ($query !== '') {
            $qb->andWhere('LOWER(e.titre) LIKE :q OR LOWER(l.nom) LIKE :q OR LOWER(l.ville) LIKE :q')
                ->setParameter('q', '%'.strtolower($query).'%');
        }

        if ($type && in_array($type, Evenement::TYPES_VALIDES, true)) {
            $qb->andWhere('e.type = :type')
                ->setParameter('type', $type);
        }

        if ($prix === 'gratuit') {
            $qb->andWhere('e.prix = 0');
        } elseif ($prix === 'payant') {
            $qb->andWhere('e.prix > 0');
        }

        return $qb->orderBy('e.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countWithFilters(
        ?string $query,
        ?string $statut,
        ?string $type,
        ?string $prix
    ): int {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->leftJoin('e.lieu', 'l');

        $query = $query ? trim($query) : '';
        if ($query !== '') {
            $qb->andWhere('LOWER(e.titre) LIKE :q OR LOWER(l.nom) LIKE :q OR LOWER(l.ville) LIKE :q')
                ->setParameter('q', '%'.strtolower($query).'%');
        }

        if ($statut && in_array($statut, Evenement::STATUTS_VALIDES, true)) {
            $qb->andWhere('e.statut = :statut')->setParameter('statut', $statut);
        }

        if ($type && in_array($type, Evenement::TYPES_VALIDES, true)) {
            $qb->andWhere('e.type = :type')->setParameter('type', $type);
        }

        if ($prix === 'gratuit') {
            $qb->andWhere('e.prix = 0');
        } elseif ($prix === 'payant') {
            $qb->andWhere('e.prix > 0');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findWithFilters(
        ?string $query,
        ?string $statut,
        ?string $type,
        ?string $prix,
        ?string $sort,
        ?string $order,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.lieu', 'l')
            ->addSelect('l');

        $query = $query ? trim($query) : '';
        if ($query !== '') {
            $qb->andWhere('LOWER(e.titre) LIKE :q OR LOWER(l.nom) LIKE :q OR LOWER(l.ville) LIKE :q')
                ->setParameter('q', '%'.strtolower($query).'%');
        }

        if ($statut && in_array($statut, Evenement::STATUTS_VALIDES, true)) {
            $qb->andWhere('e.statut = :statut')
                ->setParameter('statut', $statut);
        }

        if ($type && in_array($type, Evenement::TYPES_VALIDES, true)) {
            $qb->andWhere('e.type = :type')
                ->setParameter('type', $type);
        }

        if ($prix === 'gratuit') {
            $qb->andWhere('e.prix = 0');
        } elseif ($prix === 'payant') {
            $qb->andWhere('e.prix > 0');
        }

        $order = strtoupper($order ?? '');
        $order = $order === 'ASC' ? 'ASC' : 'DESC';

        $sortMap = [
            'date'     => 'e.dateDebut',
            'prix'     => 'e.prix',
            'capacite' => 'e.capaciteMax',
            'titre'    => 'e.titre',
        ];
        $sortField = $sortMap[$sort] ?? 'e.dateDebut';

        $qb->orderBy($sortField, $order);

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }
}