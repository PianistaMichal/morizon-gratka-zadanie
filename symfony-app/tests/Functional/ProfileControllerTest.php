<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Service\PhoenixClientInterface;
use App\Exception\InvalidPhoenixTokenException;
use App\Service\PhoenixClient;
use App\Tests\AbstractWebTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ProfileControllerTest extends AbstractWebTestCase
{
    private PhoenixClientInterface&MockObject $mockPhoenixClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client->disableReboot();

        $this->mockPhoenixClient = $this->createMock(PhoenixClientInterface::class);
        static::getContainer()->set(PhoenixClient::class, $this->mockPhoenixClient);
    }

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

    public function testSaveTokenRedirectsWhenNotLoggedIn(): void
    {
        $this->client->request('POST', '/profile/token', ['phoenix_token' => 'some-token']);

        $this->assertResponseRedirects('/');
    }

    public function testSaveTokenSavesAndRedirectsToProfile(): void
    {
        $this->loginAs('demo');

        $this->client->request('POST', '/profile/token', ['phoenix_token' => 'test_token_user1_abc123']);

        $this->assertResponseRedirects('/profile');
    }

    public function testSaveTokenShowsSuccessFlash(): void
    {
        $this->loginAs('demo');

        $this->client->request('POST', '/profile/token', ['phoenix_token' => 'test_token_user1_abc123']);
        $this->client->followRedirect();
        $content = $this->client->getResponse()->getContent();

        $this->assertStringContainsString('Token dostępu został zapisany', $content);
    }

    public function testImportRedirectsWhenNotLoggedIn(): void
    {
        $this->client->request('POST', '/profile/import');

        $this->assertResponseRedirects('/');
    }

    public function testImportWithNoTokenShowsError(): void
    {
        $this->loginAs('demo');

        $this->client->request('POST', '/profile/import');
        $this->client->followRedirect();
        $content = $this->client->getResponse()->getContent();

        $this->assertStringContainsString('Najpierw zapisz token', $content);
    }

    public function testImportWithInvalidTokenShowsError(): void
    {
        $this->loginAs('demo');

        $this->client->request('POST', '/profile/token', ['phoenix_token' => 'bad-token']);

        $this->mockPhoenixClient
            ->method('getPhotos')
            ->willThrowException(new InvalidPhoenixTokenException());

        $this->client->request('POST', '/profile/import');
        $this->client->followRedirect();
        $content = $this->client->getResponse()->getContent();

        $this->assertStringContainsString('Nieprawidłowy token', $content);
    }

    public function testImportWithValidTokenImportsPhotos(): void
    {
        $this->loginAs('demo');

        $this->client->request('POST', '/profile/token', ['phoenix_token' => 'valid-token']);

        $this->mockPhoenixClient
            ->method('getPhotos')
            ->willReturn([
                ['photo_url' => 'https://example.com/photo1.jpg'],
                ['photo_url' => 'https://example.com/photo2.jpg'],
            ]);

        $this->client->request('POST', '/profile/import');
        $this->client->followRedirect();
        $content = $this->client->getResponse()->getContent();

        $this->assertStringContainsString('Zaimportowano 2 zdjęć', $content);
    }
}
