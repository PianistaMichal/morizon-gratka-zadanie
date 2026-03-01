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

    public function testLoginThrowsUserNotFoundExceptionWhenUserDoesNotExist(): void
    {
        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')
            ->with(User::class)
            ->willReturn($userRepo);

        $this->expectException(UserNotFoundException::class);

        $this->authService->login('nonexistent', 'any-token');
    }

    public function testLoginThrowsInvalidTokenExceptionWhenTokenDoesNotBelongToUser(): void
    {
        $user = $this->createMock(User::class);

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $tokenRepo = $this->createMock(EntityRepository::class);
        // Token not found for this user
        $tokenRepo->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')
            ->willReturnMap([
                [User::class, $userRepo],
                [AuthToken::class, $tokenRepo],
            ]);

        $this->expectException(InvalidTokenException::class);

        $this->authService->login('demo', 'wrong-token');
    }

    public function testLoginCallsSessionServiceOnSuccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(42);
        $user->method('getUsername')->willReturn('demo');

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $tokenRepo = $this->createMock(EntityRepository::class);
        $tokenRepo->method('findOneBy')->willReturn(new AuthToken());

        $this->em->method('getRepository')
            ->willReturnMap([
                [User::class, $userRepo],
                [AuthToken::class, $tokenRepo],
            ]);

        $this->sessionService->expects($this->once())
            ->method('login')
            ->with(42, 'demo');

        $this->authService->login('demo', 'valid-token');
    }
}
