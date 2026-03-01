<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Photo;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

/**
 * @extends ServiceEntityRepository<Photo>
 */
class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    /**
     * @return Photo[]
     */
    public function findAllWithUsers(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<string, string> $filters
     *
     * @return Photo[]
     */
    public function findAllWithUsersFiltered(array $filters, int $page = 1, int $perPage = 12): array
    {
        return $this->buildFilteredQuery($filters)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<string, string> $filters
     */
    public function countFiltered(array $filters): int
    {
        return (int) $this->buildFilteredQuery($filters)
            ->select('COUNT(p.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array<string, string> $filters
     */
    private function buildFilteredQuery(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->orderBy('p.id', 'ASC');

        if (!empty($filters['location'])) {
            $qb->andWhere('LOWER(p.location) LIKE LOWER(:location)')
                ->setParameter('location', '%' . $filters['location'] . '%');
        }

        if (!empty($filters['camera'])) {
            $qb->andWhere('LOWER(p.camera) LIKE LOWER(:camera)')
                ->setParameter('camera', '%' . $filters['camera'] . '%');
        }

        if (!empty($filters['description'])) {
            $qb->andWhere('LOWER(p.description) LIKE LOWER(:description)')
                ->setParameter('description', '%' . $filters['description'] . '%');
        }

        if (!empty($filters['taken_at'])) {
            try {
                $date = new DateTimeImmutable($filters['taken_at'], new DateTimeZone('UTC'));
                $nextDay = $date->modify('+1 day');
                $qb->andWhere('p.takenAt >= :takenAtStart')
                    ->andWhere('p.takenAt < :takenAtEnd')
                    ->setParameter('takenAtStart', $date)
                    ->setParameter('takenAtEnd', $nextDay);
            } catch (Exception) {
                // invalid date, ignore filter
            }
        }

        if (!empty($filters['username'])) {
            $qb->andWhere('u.username LIKE :username')
                ->setParameter('username', '%' . $filters['username'] . '%');
        }

        return $qb;
    }
}
