<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\FlashService;
use App\Service\PhotoLikeService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class PhotoController
{
    public function __construct(
        private PhotoLikeService $photoLikeService,
        private RouterInterface $router,
        private FlashService $flashService,
    ) {}

    #[Route('/photo/{id}/like', name: 'photo_like')]
    public function like(int $id, Request $request): Response
    {
        $userId = $request->getSession()->get('user_id');

        if (!$userId) {
            $this->flashService->add('error', 'You must be logged in to like photos.');
            return new RedirectResponse($this->router->generate('home'));
        }

        $action = $this->photoLikeService->toggle($userId, $id);

        if ($action === 'liked') {
            $this->flashService->add('success', 'Photo liked!');
        } else {
            $this->flashService->add('info', 'Photo unliked!');
        }

        return new RedirectResponse($this->router->generate('home'));
    }
}
