<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use App\Service\FlashService;
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
    ) {}

    #[Route('/auth/{username}/{token}', name: 'auth_login')]
    public function login(string $username, string $token, Request $request): Response
    {
        if (!$this->authService->validateToken($token)) {
            return new Response('Invalid token', 401);
        }

        $user = $this->authService->findUserByUsername($username);

        if (!$user) {
            return new Response('User not found', 404);
        }

        $session = $request->getSession();
        $session->set('user_id', $user->getId());
        $session->set('username', $username);
        $this->flashService->add('success', 'Welcome back, ' . $username . '!');

        return new RedirectResponse($this->router->generate('home'));
    }

    #[Route('/logout', name: 'logout')]
    public function logout(Request $request): Response
    {
        $request->getSession()->clear();
        $this->flashService->add('info', 'You have been logged out successfully.');

        return new RedirectResponse($this->router->generate('home'));
    }
}
