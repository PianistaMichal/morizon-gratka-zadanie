<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuthToken;
use App\Entity\User;
use App\Exception\InvalidTokenException;
use App\Exception\UserNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

class AuthService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SessionService $sessionService,
    ) {
    }

    /**
     * @throws InvalidTokenException
     * @throws UserNotFoundException
     */
    public function login(string $username, string $token): void
    {
        if ($this->em->getRepository(AuthToken::class)->findOneBy(['token' => $token]) === null) {
            throw new InvalidTokenException();
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);

        if ($user === null) {
            throw new UserNotFoundException($username);
        }

        $userId = $user->getId();
        \assert($userId !== null, 'User fetched from DB must have an ID.');
        $this->sessionService->login($userId, $username);
    }
}
