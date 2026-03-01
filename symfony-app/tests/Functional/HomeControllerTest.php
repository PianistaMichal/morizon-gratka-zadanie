<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\AbstractWebTestCase;

class HomeControllerTest extends AbstractWebTestCase
{
    public function testHomePageReturnsOk(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
    }

    public function testHomePageShowsPhotos(): void
    {
        $this->client->request('GET', '/');

        $content = $this->getResponseContent();

        // Zdjęcia z fixtures zawierają te lokalizacje
        $this->assertStringContainsString('Olympic National Park', $content);
        $this->assertStringContainsString('Swiss Alps', $content);
    }

    public function testHomePageIsAccessibleWithoutLogin(): void
    {
        $this->client->request('GET', '/');

        // Niezalogowany użytkownik ma dostęp do strony głównej
        $this->assertResponseIsSuccessful();
    }

    public function testHomePageAsLoggedInUserReturnsOk(): void
    {
        $this->loginAs('demo');
        $this->client->followRedirect();

        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }
}
