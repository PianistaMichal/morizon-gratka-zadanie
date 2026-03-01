<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AbstractWebTestCase;

class ProfileControllerTest extends AbstractWebTestCase
{
    public function testProfileRedirectsWhenNotLoggedIn(): void
    {
        $this->client->request('GET', '/profile');

        $this->assertResponseRedirects('/');
    }

    public function testProfileReturnsOkWhenLoggedIn(): void
    {
        $this->loginAs('demo');

        $this->client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
    }

    public function testProfileShowsUserData(): void
    {
        $this->loginAs('demo');

        $this->client->request('GET', '/profile');
        $content = $this->client->getResponse()->getContent();

        // Fixture: demo user ma name="Demo", lastName="User", email="demo@example.com"
        $this->assertStringContainsString('Demo', $content);
        $this->assertStringContainsString('demo@example.com', $content);
    }

    public function testProfileAfterLogoutRedirects(): void
    {
        $this->loginAs('demo');

        $this->client->request('GET', '/logout');
        $this->client->followRedirect();

        $this->client->request('GET', '/profile');

        $this->assertResponseRedirects('/');
    }
}
