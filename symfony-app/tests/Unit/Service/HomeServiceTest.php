<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Photo;
use App\Entity\User;
use App\Repository\LikeRepository;
use App\Repository\PhotoRepository;
use App\Service\HomeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HomeServiceTest extends TestCase
{
    private PhotoRepository&MockObject $photoRepository;

    private LikeRepository&MockObject $likeRepository;

    private EntityManagerInterface&MockObject $em;

    private HomeService $homeService;

    protected function setUp(): void
    {
        $this->photoRepository = $this->createMock(PhotoRepository::class);
        $this->likeRepository = $this->createMock(LikeRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->homeService = new HomeService(
            $this->photoRepository,
            $this->likeRepository,
            $this->em,
        );
    }

    public function testGetPhotosDataReturnsPhotosWithoutUserDataWhenNotLoggedIn(): void
    {
        $photos = [$this->createMock(Photo::class)];
        $this->photoRepository->method('findAllWithUsersFiltered')->willReturn($photos);

        $result = $this->homeService->getPhotosData(null);

        $this->assertSame($photos, $result['photos']);
        $this->assertNull($result['currentUser']);
        $this->assertSame([], $result['userLikes']);
        $this->likeRepository->expects($this->never())->method('setUser');
    }

    public function testGetPhotosDataReturnsPhotosWithUserLikesWhenLoggedIn(): void
    {
        $user = new User();
        $photo = $this->createMock(Photo::class);
        $photo->method('getId')->willReturn(5);
        $photos = [$photo];

        $this->photoRepository->method('findAllWithUsersFiltered')->willReturn($photos);

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('find')->with(1)->willReturn($user);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $this->likeRepository->expects($this->once())->method('setUser')->with($user);
        $this->likeRepository->method('hasUserLikedPhoto')->with($photo)->willReturn(true);

        $result = $this->homeService->getPhotosData(1);

        $this->assertSame($photos, $result['photos']);
        $this->assertSame($user, $result['currentUser']);
        $this->assertSame([5 => true], $result['userLikes']);
    }

    public function testGetPhotosDataSkipsLikesWhenUserNotFoundInDb(): void
    {
        $photos = [$this->createMock(Photo::class)];
        $this->photoRepository->method('findAllWithUsersFiltered')->willReturn($photos);

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('find')->willReturn(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $this->likeRepository->expects($this->never())->method('setUser');

        $result = $this->homeService->getPhotosData(999);

        $this->assertNull($result['currentUser']);
        $this->assertSame([], $result['userLikes']);
    }

    public function testGetPhotosDataPassesFiltersToRepository(): void
    {
        $filters = ['location' => 'Paris', 'camera' => 'Canon'];
        $this->photoRepository->expects($this->once())
            ->method('findAllWithUsersFiltered')
            ->with($filters)
            ->willReturn([]);

        $this->homeService->getPhotosData(null, $filters);
    }
}
