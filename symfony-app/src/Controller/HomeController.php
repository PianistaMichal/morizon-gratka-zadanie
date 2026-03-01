<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\HomeService;
use App\Service\SessionService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class HomeController
{
    public function __construct(
        private HomeService $homeService,
        private SessionService $sessionService,
        private Environment $twig,
    ) {
    }

    #[Route('/', name: 'home')]
    public function index(Request $request): Response
    {
        $userId = $this->sessionService->getUserId();

        $filters = array_filter([
            'location' => $request->query->get('location', ''),
            'camera' => $request->query->get('camera', ''),
            'description' => $request->query->get('description', ''),
            'taken_at' => $request->query->get('taken_at', ''),
            'username' => $request->query->get('username', ''),
        ]);

        $data = $this->homeService->getPhotosData($userId, $filters);
        $data['filters'] = $request->query->all();

        return new Response($this->twig->render('home/index.html.twig', $data));
    }
}
