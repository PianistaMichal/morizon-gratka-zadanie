<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AbstractWebTestCase;

class AuthControllerTest extends AbstractWebTestCase
{
    private const DEMO_TOKEN = 'demo1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab';
    private const INVALID_TOKEN = 'invalidtoken000000000000000000000000000000000000000000000000000000';

    public function testLoginWithValidCredentials(): void
    {
        $this->client->request('GET', '/auth/demo/' . self::DEMO_TOKEN);

        $this->assertResponseRedirects('/');
    }

    public function testLoginSetsSessionAndRedirects(): void
    {
        $this->client->request('GET', '/auth/demo/' . self::DEMO_TOKEN);
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
    }

    public function testLoginWithInvalidTokenReturns401(): void
    {
        $this->client->request('GET', '/auth/demo/' . self::INVALID_TOKEN);

        $this->assertResponseStatusCodeSame(401);
        $this->assertStringContainsString('Invalid token', $this->client->getResponse()->getContent());
    }

    public function testLoginWithValidTokenButNonexistentUserReturns404(): void
    {
        $this->client->request('GET', '/auth/nonexistent_user/' . self::DEMO_TOKEN);

        $this->assertResponseStatusCodeSame(404);
        $this->assertStringContainsString('User not found', $this->client->getResponse()->getContent());
    }

    public function testLogoutRedirectsToHome(): void
    {
        $this->loginAs('demo');

        $this->client->request('GET', '/logout');

        $this->assertResponseRedirects('/');
    }

    public function testLogoutClearsSession(): void
    {
        $this->loginAs('demo');

        $this->client->request('GET', '/logout');
        $this->client->followRedirect();

        // Po wylogowaniu /profile powinno przekierować do strony głównej
        $this->client->request('GET', '/profile');
        $this->assertResponseRedirects('/');
    }

    public function testLoginWithAnotherExistingUser(): void
    {
        $this->client->request('GET', '/auth/nature_lover/' . self::DEMO_TOKEN);

        $this->assertResponseRedirects('/');
    }
}
