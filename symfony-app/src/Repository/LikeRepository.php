<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Like;
use App\Entity\Photo;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Override;

/**
 * @extends ServiceEntityRepository<Like>
 */
class LikeRepository extends ServiceEntityRepository implements LikeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Like::class);
    }

    #[Override]
    public function hasUserLikedPhoto(User $user, Photo $photo): bool
    {
        return (bool) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.user = :user')
            ->andWhere('l.photo = :photo')
            ->setParameter('user', $user)
            ->setParameter('photo', $photo)
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[Override]
    public function getUserLikesForPhotoIds(User $user, array $photoIds): array
    {
        if (empty($photoIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.photo) as photoId')
            ->where('l.user = :user')
            ->andWhere('l.photo IN (:photoIds)')
            ->setParameter('user', $user)
            ->setParameter('photoIds', $photoIds)
            ->getQuery()
            ->getScalarResult();

        $likedSet = array_flip(array_column($rows, 'photoId'));

        $result = [];
        foreach ($photoIds as $id) {
            $result[$id] = isset($likedSet[$id]);
        }

        return $result;
    }

    #[Override]
    public function createLike(User $user, Photo $photo): Like
    {
        $like = new Like();
        $like->setUser($user);
        $like->setPhoto($photo);

        $em = $this->getEntityManager();
        $em->persist($like);
        $em->flush();

        return $like;
    }

    #[Override]
    public function unlikePhoto(User $user, Photo $photo): void
    {
        $em = $this->getEntityManager();

        $like = $em->createQueryBuilder()
            ->select('l')
            ->from(Like::class, 'l')
            ->where('l.user = :user')
            ->andWhere('l.photo = :photo')
            ->setParameter('user', $user)
            ->setParameter('photo', $photo)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($like) {
            $em->remove($like);
            $em->flush();

            $photo->setLikeCounter($photo->getLikeCounter() - 1);
            $em->persist($photo);
            $em->flush();
        }
    }

    #[Override]
    public function updatePhotoCounter(Photo $photo, int $increment): void
    {
        $em = $this->getEntityManager();
        $photo->setLikeCounter($photo->getLikeCounter() + $increment);
        $em->persist($photo);
        $em->flush();
    }
}
