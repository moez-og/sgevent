<?php

namespace App\Repository;

use App\Entity\Lieu;
use App\Enum\LieuCategorie;
use App\Enum\LieuType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lieu>
 */
class LieuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lieu::class);
    }

    public function createFilteredQueryBuilder(array $filters = []): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('l');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $queryBuilder
                ->andWhere('LOWER(l.nom) LIKE :search OR LOWER(l.ville) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        $categorie = $filters['categorie'] ?? null;
        if ($categorie instanceof LieuCategorie) {
            $queryBuilder
                ->andWhere('l.categorie = :categorie')
                ->setParameter('categorie', $categorie);
        }

        $type = $filters['type'] ?? null;
        if ($type instanceof LieuType) {
            $queryBuilder
                ->andWhere('l.type = :type')
                ->setParameter('type', $type);
        }

        $sortMap = [
            'id' => 'l.id',
            'nom' => 'l.nom',
            'ville' => 'l.ville',
            'categorie' => 'l.categorie',
            'type' => 'l.type',
            'budget' => 'l.budget_min',
        ];

        $sort = (string) ($filters['sort'] ?? 'id');
        $direction = strtoupper((string) ($filters['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $queryBuilder->orderBy($sortMap[$sort] ?? 'l.id', $direction);

        return $queryBuilder;
    }

    public function paginateFiltered(array $filters, int $page, int $limit): Paginator
    {
        $query = $this->createFilteredQueryBuilder($filters)
            ->getQuery()
            ->setFirstResult(max(0, $page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($query, true);
    }

    public function findDetailed(int $id): ?Lieu
    {
        return $this->createQueryBuilder('l')
            ->distinct()
            ->leftJoin('l.offre', 'o')->addSelect('o')
            ->leftJoin('l.evaluationLieu', 'e')->addSelect('e')
            ->leftJoin('l.lieuHoraire', 'h')->addSelect('h')
            ->leftJoin('l.lieuImages', 'i')->addSelect('i')
            ->andWhere('l.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
