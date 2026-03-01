<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Photo;
use App\Repository\LikeRepositoryInterface;
use App\Service\LikeService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LikeServiceTest extends TestCase
{
    private LikeRepositoryInterface&MockObject $likeRepository;
    private LikeService $likeService;

    protected function setUp(): void
    {
        $this->likeRepository = $this->createMock(LikeRepositoryInterface::class);
        $this->likeService = new LikeService($this->likeRepository);
    }

    public function testExecuteCallsCreateLikeAndUpdatePhotoCounter(): void
    {
        $photo = $this->createMock(Photo::class);

        $this->likeRepository->expects($this->once())
            ->method('createLike')
            ->with($photo);

        $this->likeRepository->expects($this->once())
            ->method('updatePhotoCounter')
            ->with($photo, 1);

        $this->likeService->execute($photo);
    }

    public function testExecuteThrowsExceptionWhenRepositoryFails(): void
    {
        $photo = $this->createMock(Photo::class);

        $this->likeRepository->method('createLike')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Something went wrong while liking the photo');

        $this->likeService->execute($photo);
    }
}
