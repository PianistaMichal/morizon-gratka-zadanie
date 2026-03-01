<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\InvalidPhoenixTokenException;

interface PhoenixClientInterface
{
    /**
     * @throws InvalidPhoenixTokenException
     *
     * @return array<array{photo_url: string}>
     */
    public function getPhotos(string $token): array;
}
