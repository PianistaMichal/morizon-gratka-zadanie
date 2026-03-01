<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\SessionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionServiceTest extends TestCase
{
    private RequestStack&MockObject $requestStack;

    private SessionInterface&MockObject $session;

    private SessionService $sessionService;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->session = $this->createMock(SessionInterface::class);
        $this->requestStack->method('getSession')->willReturn($this->session);

        $this->sessionService = new SessionService($this->requestStack);
    }

    public function testGetUserIdReturnsValueFromSession(): void
    {
        $this->session->method('get')->with('user_id')->willReturn(42);

        $this->assertSame(42, $this->sessionService->getUserId());
    }

    public function testGetUserIdReturnsNullWhenNotSet(): void
    {
        $this->session->method('get')->with('user_id')->willReturn(null);

        $this->assertNull($this->sessionService->getUserId());
    }

    public function testLoginSetsUserIdAndUsernameInSession(): void
    {
        $setCalls = [];
        $this->session->method('set')
            ->willReturnCallback(static function (string $key, mixed $value) use (&$setCalls): void {
                $setCalls[$key] = $value;
            });

        $this->sessionService->login(42, 'testuser');

        $this->assertSame(42, $setCalls['user_id']);
        $this->assertSame('testuser', $setCalls['username']);
    }

    public function testLogoutClearsSession(): void
    {
        $this->session->expects($this->once())->method('clear');

        $this->sessionService->logout();
    }
}
