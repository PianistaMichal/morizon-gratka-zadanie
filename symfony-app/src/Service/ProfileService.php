<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ProfileService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function findUser(int $userId): ?User
    {
        return $this->em->getRepository(User::class)->find($userId);
    }
}
