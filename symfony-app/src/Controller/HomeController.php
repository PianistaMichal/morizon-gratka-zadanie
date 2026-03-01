<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\HomeService;
use App\Service\SessionService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class HomeController
{
    public function __construct(
        private HomeService $homeService,
        private SessionService $sessionService,
        private Environment $twig,
    ) {}

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        $userId = $this->sessionService->getUserId();
        $data = $this->homeService->getPhotosData($userId);

        return new Response($this->twig->render('home/index.html.twig', $data));
    }
}
