<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\FlashType;
use App\Service\AuthService;
use App\Service\FlashService;
use App\Service\SessionService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class AuthController
{
    public function __construct(
        private AuthService $authService,
        private RouterInterface $router,
        private FlashService $flashService,
        private SessionService $sessionService,
    ) {}

    #[Route('/auth/{username}/{token}', name: 'auth_login')]
    public function login(string $username, string $token): Response
    {
        if (!$this->authService->validateToken($token)) {
            return new Response('Invalid token', 401);
        }

        $user = $this->authService->findUserByUsername($username);

        if (!$user) {
            return new Response('User not found', 404);
        }

        $this->sessionService->login($user->getId(), $username);
        $this->flashService->add(FlashType::SUCCESS, "Welcome back, {$username}!");

        return new RedirectResponse($this->router->generate('home'));
    }

    #[Route('/logout', name: 'logout')]
    public function logout(Request $request): Response
    {
        $request->getSession()->clear();
        $this->flashService->add(FlashType::INFO, 'You have been logged out successfully.');

        return new RedirectResponse($this->router->generate('home'));
    }
}
