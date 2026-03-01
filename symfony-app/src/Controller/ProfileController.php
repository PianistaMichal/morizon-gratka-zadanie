<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\FlashType;
use App\Exception\InvalidPhoenixTokenException;
use App\Service\FlashService;
use App\Service\PhoenixClientInterface;
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
        private FlashService $flashService,
        private PhoenixClientInterface $phoenixClient,
    ) {
    }

    #[Route('/profile', name: 'profile')]
    public function profile(Request $request): Response
    {
        $userId = $this->sessionService->getUserId();

        if ($userId === null) {
            return new RedirectResponse($this->router->generate('home'));
        }

        $user = $this->profileService->findUser($userId);

        if ($user === null) {
            $request->getSession()->clear();

            return new RedirectResponse($this->router->generate('home'));
        }

        return new Response($this->twig->render('profile/index.html.twig', ['user' => $user]));
    }

    #[Route('/profile/token', name: 'profile_save_token', methods: ['POST'])]
    public function saveToken(Request $request): Response
    {
        $userId = $this->sessionService->getUserId();

        if ($userId === null) {
            return new RedirectResponse($this->router->generate('home'));
        }

        $user = $this->profileService->findUser($userId);

        if ($user === null) {
            $request->getSession()->clear();

            return new RedirectResponse($this->router->generate('home'));
        }

        $token = trim($request->request->getString('phoenix_token'));
        $this->profileService->savePhoenixToken($user, $token);
        $this->flashService->add(FlashType::SUCCESS, 'Token dostępu został zapisany.');

        return new RedirectResponse($this->router->generate('profile'));
    }

    #[Route('/profile/import', name: 'profile_import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        $userId = $this->sessionService->getUserId();

        if ($userId === null) {
            return new RedirectResponse($this->router->generate('home'));
        }

        $user = $this->profileService->findUser($userId);

        if ($user === null) {
            $request->getSession()->clear();

            return new RedirectResponse($this->router->generate('home'));
        }

        if ($user->getPhoenixToken() === null) {
            $this->flashService->add(FlashType::ERROR, 'Najpierw zapisz token dostępu do PhoenixApi.');

            return new RedirectResponse($this->router->generate('profile'));
        }

        try {
            $count = $this->profileService->importPhotos($user, $this->phoenixClient);
            $this->flashService->add(FlashType::SUCCESS, "Zaimportowano {$count} zdjęć.");
        } catch (InvalidPhoenixTokenException) {
            $this->flashService->add(FlashType::ERROR, 'Nieprawidłowy token dostępu do PhoenixApi.');
        }

        return new RedirectResponse($this->router->generate('profile'));
    }
}
