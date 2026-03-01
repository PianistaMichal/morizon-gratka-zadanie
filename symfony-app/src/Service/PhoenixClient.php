<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\InvalidPhoenixTokenException;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PhoenixClient implements PhoenixClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
    ) {
    }

    public function getPhotos(string $token): array
    {
        $response = $this->httpClient->request('GET', $this->baseUrl . '/api/photos', [
            'headers' => ['access-token' => $token],
            'timeout' => 10.0,
        ]);

        if ($response->getStatusCode() === 401) {
            throw new InvalidPhoenixTokenException();
        }

        $data = $response->toArray();

        if (!array_key_exists('photos', $data) || !is_array($data['photos'])) {
            throw new RuntimeException('Unexpected response structure from PhoenixApi');
        }

        return $data['photos'];
    }
}
