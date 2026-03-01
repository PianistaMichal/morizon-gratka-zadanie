<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Photo;
use App\Entity\User;
use App\Enum\LikeAction;
use App\Repository\LikeRepository;
use App\Service\LikeService;
use App\Service\PhotoLikeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PhotoLikeServiceTest extends TestCase
{
    private LikeRepository&MockObject $likeRepository;
    private LikeService&MockObject $likeService;
    private EntityManagerInterface&MockObject $em;
    private PhotoLikeService $photoLikeService;

    protected function setUp(): void
    {
        $this->likeRepository = $this->createMock(LikeRepository::class);
        $this->likeService = $this->createMock(LikeService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->photoLikeService = new PhotoLikeService(
            $this->likeRepository,
            $this->likeService,
            $this->em,
        );
    }

    private function setupEmRepositories(?User $user, ?Photo $photo): void
    {
        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('find')->willReturn($user);

        $photoRepo = $this->createMock(EntityRepository::class);
        $photoRepo->method('find')->willReturn($photo);

        $this->em->method('getRepository')
            ->willReturnMap([
                [User::class, $userRepo],
                [Photo::class, $photoRepo],
            ]);
    }

    public function testToggleThrowsNotFoundExceptionWhenPhotoNotFound(): void
    {
        $this->setupEmRepositories(new User(), null);

        $this->expectException(NotFoundHttpException::class);

        $this->photoLikeService->toggle(1, 99);
    }

    public function testToggleUnlikesWhenUserHasAlreadyLiked(): void
    {
        $user = new User();
        $photo = $this->createMock(Photo::class);

        $this->setupEmRepositories($user, $photo);

        $this->likeRepository->expects($this->once())->method('setUser')->with($user);
        $this->likeRepository->method('hasUserLikedPhoto')->with($photo)->willReturn(true);
        $this->likeRepository->expects($this->once())->method('unlikePhoto')->with($photo);
        $this->likeService->expects($this->never())->method('execute');

        $result = $this->photoLikeService->toggle(1, 1);

        $this->assertSame(LikeAction::UNLIKED, $result);
    }

    public function testToggleLikesWhenUserHasNotLiked(): void
    {
        $user = new User();
        $photo = $this->createMock(Photo::class);

        $this->setupEmRepositories($user, $photo);

        $this->likeRepository->expects($this->once())->method('setUser')->with($user);
        $this->likeRepository->method('hasUserLikedPhoto')->with($photo)->willReturn(false);
        $this->likeRepository->expects($this->never())->method('unlikePhoto');
        $this->likeService->expects($this->once())->method('execute')->with($photo);

        $result = $this->photoLikeService->toggle(1, 1);

        $this->assertSame(LikeAction::LIKED, $result);
    }
}
