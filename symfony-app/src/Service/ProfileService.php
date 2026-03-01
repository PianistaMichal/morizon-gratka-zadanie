<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Photo;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ProfileService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function findUser(int $userId): ?User
    {
        return $this->em->getRepository(User::class)->find($userId);
    }

    public function savePhoenixToken(User $user, string $token): void
    {
        $user->setPhoenixToken($token ?: null);
        $this->em->flush();
    }

    public function importPhotos(User $user, PhoenixClientInterface $phoenixClient): int
    {
        $photos = $phoenixClient->getPhotos($user->getPhoenixToken() ?? '');

        $count = 0;
        foreach ($photos as $photoData) {
            $photo = (new Photo())->setImageUrl($photoData['photo_url']);
            $user->addPhoto($photo);
            $this->em->persist($photo);
            ++$count;
        }

        $this->em->flush();

        return $count;
    }
}
