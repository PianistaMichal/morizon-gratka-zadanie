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
     * @throws UserNotFoundException
     * @throws InvalidTokenException
     */
    public function login(string $username, string $token): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);

        if ($user === null) {
            throw new UserNotFoundException($username);
        }

        // Token must belong to the requesting user, not just exist in the database.
        $authToken = $this->em->getRepository(AuthToken::class)->findOneBy([
            'token' => $token,
            'user' => $user,
        ]);

        if ($authToken === null) {
            throw new InvalidTokenException();
        }

        $userId = $user->getId();
        assert($userId !== null, 'User fetched from DB must have an ID.');
        $this->sessionService->login($userId, $username);
    }
}
