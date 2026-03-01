<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Exception\InvalidPhoenixTokenException;

interface PhoenixClientInterface
{
    /**
     * @return array<array{photo_url: string}>
     * @throws InvalidPhoenixTokenException
     */
    public function getPhotos(string $token): array;
}
