<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Port\PhoenixClientInterface;
use App\Exception\InvalidPhoenixTokenException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PhoenixClient implements PhoenixClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
    ) {}

    public function getPhotos(string $token): array
    {
        $response = $this->httpClient->request('GET', $this->baseUrl . '/api/photos', [
            'headers' => ['access-token' => $token],
        ]);

        if ($response->getStatusCode() === 401) {
            throw new InvalidPhoenixTokenException();
        }

        return $response->toArray()['photos'] ?? [];
    }
}
