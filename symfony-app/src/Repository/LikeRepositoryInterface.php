<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Like;
use App\Entity\Photo;
use App\Entity\User;

interface LikeRepositoryInterface
{
    public function hasUserLikedPhoto(User $user, Photo $photo): bool;

    public function createLike(User $user, Photo $photo): Like;

    public function unlikePhoto(User $user, Photo $photo): void;

    public function updatePhotoCounter(Photo $photo, int $increment): void;
}
