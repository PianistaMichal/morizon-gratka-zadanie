<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\AbstractWebTestCase;

class AuthControllerTest extends AbstractWebTestCase
{
    private const DEMO_TOKEN = 'demo1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab';

    private const INVALID_TOKEN = 'invalidtoken000000000000000000000000000000000000000000000000000000';

    public function testLoginWithValidCredentialsRedirects(): void
    {
        $this->client->request('POST', '/auth', [
            'username' => 'demo',
            'token' => self::DEMO_TOKEN,
        ]);

        $this->assertResponseRedirects('/');
    }

    public function testLoginSetsSessionAndRedirects(): void
    {
        $this->client->request('POST', '/auth', [
            'username' => 'demo',
            'token' => self::DEMO_TOKEN,
        ]);
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
    }

    public function testLoginWithInvalidTokenReturns401(): void
    {
        $this->client->request('POST', '/auth', [
            'username' => 'demo',
            'token' => self::INVALID_TOKEN,
        ]);

        $this->assertResponseStatusCodeSame(401);
        $this->assertStringContainsString('Invalid token', $this->getResponseContent());
    }

    public function testLoginWithNonexistentUserReturns404(): void
    {
        $this->client->request('POST', '/auth', [
            'username' => 'nonexistent_user',
            'token' => self::DEMO_TOKEN,
        ]);

        $this->assertResponseStatusCodeSame(404);
        $this->assertStringContainsString('User not found', $this->getResponseContent());
    }

    public function testLoginWithTokenBelongingToAnotherUserReturns401(): void
    {
        // DEMO_TOKEN belongs to 'demo', not 'nature_lover' — must be rejected
        $this->client->request('POST', '/auth', [
            'username' => 'nature_lover',
            'token' => self::DEMO_TOKEN,
        ]);

        $this->assertResponseStatusCodeSame(401);
        $this->assertStringContainsString('Invalid token', $this->getResponseContent());
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
}
