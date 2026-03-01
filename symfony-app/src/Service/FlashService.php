<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\FlashType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class FlashService
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function add(FlashType $type, string $message): void
    {
        $flashBag = $this->requestStack->getSession()->getBag('flashes');
        if ($flashBag instanceof FlashBagInterface) {
            $flashBag->add($type->value, $message);
        }
    }
}
