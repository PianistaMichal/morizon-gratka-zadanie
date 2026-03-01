<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Likes\LikeRepository;
use App\Repository\PhotoRepository;
use Doctrine\ORM\EntityManagerInterface;

class HomeService
{
    public function __construct(
        private PhotoRepository $photoRepository,
        private LikeRepository $likeRepository,
        private EntityManagerInterface $em,
    ) {}

    public function getPhotosData(?int $userId): array
    {
        $photos = $this->photoRepository->findAllWithUsers();
        $currentUser = null;
        $userLikes = [];

        if ($userId) {
            $currentUser = $this->em->getRepository(User::class)->find($userId);

            if ($currentUser) {
                $this->likeRepository->setUser($currentUser);
                foreach ($photos as $photo) {
                    $userLikes[$photo->getId()] = $this->likeRepository->hasUserLikedPhoto($photo);
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
