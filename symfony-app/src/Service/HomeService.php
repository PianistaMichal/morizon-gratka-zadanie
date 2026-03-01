<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Photo;
use App\Entity\User;
use App\Repository\LikeRepository;
use App\Repository\PhotoRepository;
use Doctrine\ORM\EntityManagerInterface;

class HomeService
{
    public function __construct(
        private PhotoRepository $photoRepository,
        private LikeRepository $likeRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array{photos: Photo[], currentUser: User|null, userLikes: array<int, bool>}
     */
    public function getPhotosData(?int $userId, array $filters = []): array
    {
        $photos = $this->photoRepository->findAllWithUsersFiltered($filters);
        $currentUser = null;
        $userLikes = [];

        if ($userId) {
            $currentUser = $this->em->getRepository(User::class)->find($userId);

            if ($currentUser) {
                foreach ($photos as $photo) {
                    $userLikes[(int) $photo->getId()] = $this->likeRepository->hasUserLikedPhoto($currentUser, $photo);
                }
            }
        }

        return [
            'photos' => $photos,
            'currentUser' => $currentUser,
            'userLikes' => $userLikes,
        ];
    }
}
