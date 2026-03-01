<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuthToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AuthService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function validateToken(string $token): bool
    {
        return $this->em->getRepository(AuthToken::class)->findOneBy(['token' => $token]) !== null;
    }

    public function findUserByUsername(string $username): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
    }
}
