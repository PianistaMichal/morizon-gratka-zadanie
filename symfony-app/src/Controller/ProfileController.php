<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ProfileService;
use App\Service\SessionService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class ProfileController
{
    public function __construct(
        private ProfileService $profileService,
        private SessionService $sessionService,
        private RouterInterface $router,
        private Environment $twig,
    ) {}

    #[Route('/profile', name: 'profile')]
    public function profile(Request $request): Response
    {
        $userId = $this->sessionService->getUserId();

        if (!$userId) {
            return new RedirectResponse($this->router->generate('home'));
        }

        $user = $this->profileService->findUser($userId);

        if (!$user) {
            $request->getSession()->clear();
            return new RedirectResponse($this->router->generate('home'));
        }

        return new Response($this->twig->render('profile/index.html.twig', ['user' => $user]));
    }
}
