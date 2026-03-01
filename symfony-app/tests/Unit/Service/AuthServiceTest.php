<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\AuthToken;
use App\Entity\User;
use App\Exception\InvalidTokenException;
use App\Exception\UserNotFoundException;
use App\Service\AuthService;
use App\Service\SessionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private SessionService&MockObject $sessionService;
    private AuthService $authService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->sessionService = $this->createMock(SessionService::class);
        $this->authService = new AuthService($this->em, $this->sessionService);
    }

    public function testLoginThrowsInvalidTokenExceptionWhenTokenNotFound(): void
    {
        $tokenRepo = $this->createMock(EntityRepository::class);
        $tokenRepo->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')
            ->with(AuthToken::class)
            ->willReturn($tokenRepo);

        $this->expectException(InvalidTokenException::class);

        $this->authService->login('user', 'invalid-token');
    }

    public function testLoginThrowsUserNotFoundExceptionWhenUserDoesNotExist(): void
    {
        $tokenRepo = $this->createMock(EntityRepository::class);
        $tokenRepo->method('findOneBy')->willReturn(new AuthToken());

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')
            ->willReturnMap([
                [AuthToken::class, $tokenRepo],
                [User::class, $userRepo],
            ]);

        $this->expectException(UserNotFoundException::class);

        $this->authService->login('nonexistent', 'valid-token');
    }

    public function testLoginCallsSessionServiceOnSuccess(): void
    {
        $tokenRepo = $this->createMock(EntityRepository::class);
        $tokenRepo->method('findOneBy')->willReturn(new AuthToken());

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(42);
        $user->method('getUsername')->willReturn('testuser');

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $this->em->method('getRepository')
            ->willReturnMap([
                [AuthToken::class, $tokenRepo],
                [User::class, $userRepo],
            ]);

        $this->sessionService->expects($this->once())
            ->method('login')
            ->with(42, 'testuser');

        $this->authService->login('testuser', 'valid-token');
    }
}
