<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class SessionService
{
    private const USER_ID = 'user_id';
    private const USERNAME = 'username';

    public function __construct(
        private RequestStack $requestStack,
    ) {}

    public function getUserId(): ?int
    {
        return $this->requestStack->getSession()->get(self::USER_ID);
    }

    public function login(int $userId, string $username): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::USER_ID, $userId);
        $session->set(self::USERNAME, $username);
    }
}
