<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Photo;
use App\Entity\User;
use App\Likes\LikeRepository;
use App\Likes\LikeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PhotoLikeService
{
    public function __construct(
        private LikeRepository $likeRepository,
        private LikeService $likeService,
        private EntityManagerInterface $em,
    ) {}

    public function toggle(int $userId, int $photoId): string
    {
        $user = $this->em->getRepository(User::class)->find($userId);
        $photo = $this->em->getRepository(Photo::class)->find($photoId);

        if (!$photo) {
            throw new NotFoundHttpException('Photo not found');
        }

        $this->likeRepository->setUser($user);

        if ($this->likeRepository->hasUserLikedPhoto($photo)) {
            $this->likeRepository->unlikePhoto($photo);
            return 'unliked';
        }

        $this->likeService->execute($photo);
        return 'liked';
    }
}
