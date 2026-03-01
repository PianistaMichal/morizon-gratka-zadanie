<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\PhoenixClientInterface;
use App\Service\ProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProfileServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;

    private ProfileService $profileService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->profileService = new ProfileService($this->em);
    }

    public function testFindUserReturnsUser(): void
    {
        $user = new User();

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('find')->with(1)->willReturn($user);

        $this->em->method('getRepository')
            ->with(User::class)
            ->willReturn($userRepo);

        $this->assertSame($user, $this->profileService->findUser(1));
    }

    public function testFindUserReturnsNullWhenNotFound(): void
    {
        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('find')->willReturn(null);

        $this->em->method('getRepository')
            ->with(User::class)
            ->willReturn($userRepo);

        $this->assertNull($this->profileService->findUser(99));
    }

    public function testSavePhoenixTokenSetsTokenAndFlushes(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())
            ->method('setPhoenixToken')
            ->with('mytoken');

        $this->em->expects($this->once())->method('flush');

        $this->profileService->savePhoenixToken($user, 'mytoken');
    }

    public function testSavePhoenixTokenSetsNullWhenEmptyString(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())
            ->method('setPhoenixToken')
            ->with(null);

        $this->em->expects($this->once())->method('flush');

        $this->profileService->savePhoenixToken($user, '');
    }

    public function testImportPhotosImportsAndReturnsCount(): void
    {
        $user = new User();

        $phoenixClient = $this->createMock(PhoenixClientInterface::class);
        $phoenixClient->method('getPhotos')
            ->willReturn([
                ['photo_url' => 'https://example.com/1.jpg'],
                ['photo_url' => 'https://example.com/2.jpg'],
            ]);

        $this->em->expects($this->exactly(2))->method('persist');
        $this->em->expects($this->once())->method('flush');

        $count = $this->profileService->importPhotos($user, $phoenixClient);

        $this->assertSame(2, $count);
        $this->assertCount(2, $user->getPhotos());
    }

    public function testImportPhotosReturnsZeroWhenNoPhotos(): void
    {
        $user = new User();

        $phoenixClient = $this->createMock(PhoenixClientInterface::class);
        $phoenixClient->method('getPhotos')->willReturn([]);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $count = $this->profileService->importPhotos($user, $phoenixClient);

        $this->assertSame(0, $count);
    }
}
