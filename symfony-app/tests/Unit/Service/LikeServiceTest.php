<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Photo;
use App\Entity\User;
use App\Repository\LikeRepositoryInterface;
use App\Service\LikeService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
        $user = new User();
        $photo = $this->createMock(Photo::class);

        $this->likeRepository->expects($this->once())
            ->method('createLike')
            ->with($user, $photo);

        $this->likeRepository->expects($this->once())
            ->method('updatePhotoCounter')
            ->with($photo, 1);

        $this->likeService->execute($user, $photo);
    }

    public function testExecuteThrowsRuntimeExceptionWithPreviousWhenRepositoryFails(): void
    {
        $user = new User();
        $photo = $this->createMock(Photo::class);

        $original = new RuntimeException('DB error');
        $this->likeRepository->method('createLike')
            ->willThrowException($original);

        try {
            $this->likeService->execute($user, $photo);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('Something went wrong while liking the photo', $e->getMessage());
            // Ensure the original exception is preserved for debugging
            $this->assertSame($original, $e->getPrevious());
        }
    }
}
