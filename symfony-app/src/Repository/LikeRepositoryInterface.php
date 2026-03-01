<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Like;
use App\Entity\Photo;
use App\Entity\User;

interface LikeRepositoryInterface
{
    public function hasUserLikedPhoto(User $user, Photo $photo): bool;

    /**
     * Returns a map of photoId => bool for all given photo IDs in a single query.
     * Avoids N+1 when checking likes for a list of photos.
     *
     * @param int[] $photoIds
     *
     * @return array<int, bool>
     */
    public function getUserLikesForPhotoIds(User $user, array $photoIds): array;

    public function createLike(User $user, Photo $photo): Like;

    public function unlikePhoto(User $user, Photo $photo): void;

    public function updatePhotoCounter(Photo $photo, int $increment): void;
}
