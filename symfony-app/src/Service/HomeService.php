<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Photo;
use App\Entity\User;
use App\Repository\LikeRepositoryInterface;
use App\Repository\PhotoRepository;
use Doctrine\ORM\EntityManagerInterface;

class HomeService
{
    private const PER_PAGE = 12;

    public function __construct(
        private PhotoRepository $photoRepository,
        private LikeRepositoryInterface $likeRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array{photos: Photo[], currentUser: User|null, userLikes: array<int, bool>, currentPage: int, totalPages: int}
     */
    public function getPhotosData(?int $userId, array $filters = [], int $page = 1): array
    {
        $photos = $this->photoRepository->findAllWithUsersFiltered($filters, $page, self::PER_PAGE);
        $total = $this->photoRepository->countFiltered($filters);
        $currentUser = null;
        $userLikes = [];

        if ($userId) {
            $currentUser = $this->em->getRepository(User::class)->find($userId);

            if ($currentUser) {
                $photoIds = array_map(static fn (Photo $p) => (int) $p->getId(), $photos);
                $userLikes = $this->likeRepository->getUserLikesForPhotoIds($currentUser, $photoIds);
            }
        }

        return [
            'photos' => $photos,
            'currentUser' => $currentUser,
            'userLikes' => $userLikes,
            'currentPage' => $page,
            'totalPages' => (int) ceil($total / self::PER_PAGE),
        ];
    }
}
