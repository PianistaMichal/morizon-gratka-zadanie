<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\FlashType;
use App\Enum\LikeAction;
use App\Service\FlashService;
use App\Service\PhotoLikeService;
use App\Service\SessionService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class PhotoController
{
    public function __construct(
        private PhotoLikeService $photoLikeService,
        private RouterInterface $router,
        private FlashService $flashService,
        private SessionService $sessionService,
    ) {}

    #[Route('/photo/{id}/like', name: 'photo_like')]
    public function like(int $id): Response
    {
        $userId = $this->sessionService->getUserId();

        if (!$userId) {
            $this->flashService->add(FlashType::ERROR, 'You must be logged in to like photos.');
            return new RedirectResponse($this->router->generate('home'));
        }

        $action = $this->photoLikeService->toggle($userId, $id);

        if ($action === LikeAction::LIKED) {
            $this->flashService->add(FlashType::SUCCESS, 'Photo liked!');
        } else {
            $this->flashService->add(FlashType::INFO, 'Photo unliked!');
        }

        return new RedirectResponse($this->router->generate('home'));
    }
}
