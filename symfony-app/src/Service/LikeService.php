<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Photo;
use App\Entity\User;
use App\Repository\LikeRepositoryInterface;
use RuntimeException;
use Throwable;

class LikeService
{
    public function __construct(
        private LikeRepositoryInterface $likeRepository,
    ) {
    }

    public function execute(User $user, Photo $photo): void
    {
        try {
            $this->likeRepository->createLike($user, $photo);
            $this->likeRepository->updatePhotoCounter($photo, 1);
        } catch (Throwable $e) {
            throw new RuntimeException('Something went wrong while liking the photo', 0, $e);
        }
    }
}
