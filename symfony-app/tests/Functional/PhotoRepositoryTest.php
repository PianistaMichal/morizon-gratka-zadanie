<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\PhotoRepository;
use App\Tests\AbstractWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class PhotoRepositoryTest extends AbstractWebTestCase
{
    private PhotoRepository $photoRepository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->photoRepository = $em->getRepository(\App\Entity\Photo::class);
    }

    public function testNoFiltersReturnsAllPhotos(): void
    {
        $photos = $this->photoRepository->findAllWithUsersFiltered([]);

        // Fixtures zawierają 12 zdjęć, domyślny perPage = 12
        $this->assertCount(12, $photos);
    }

    public function testFilterByLocationReturnsMatchingPhotos(): void
    {
        $photos = $this->photoRepository->findAllWithUsersFiltered(['location' => 'Alaska']);

        $this->assertCount(1, $photos);
        $this->assertNotNull($photos[0]->getLocation());
        $this->assertStringContainsString('Alaska', $photos[0]->getLocation());
    }

    public function testFilterByCameraReturnsMatchingPhotos(): void
    {
        // Fixture: "Nikon D6" — tylko jedno zdjęcie (bear photo)
        $photos = $this->photoRepository->findAllWithUsersFiltered(['camera' => 'Nikon D6']);

        $this->assertCount(1, $photos);
        $this->assertSame('Nikon D6', $photos[0]->getCamera());
    }

    public function testFilterByCameraIsCaseInsensitive(): void
    {
        $photosLower = $this->photoRepository->findAllWithUsersFiltered(['camera' => 'nikon d6']);
        $photosUpper = $this->photoRepository->findAllWithUsersFiltered(['camera' => 'NIKON D6']);

        $this->assertCount(1, $photosLower);
        $this->assertCount(1, $photosUpper);
    }

    public function testFilterByUsernameReturnsDemoPhotos(): void
    {
        // Fixture: userIndex 0 (demo) ma 3 zdjęcia: forest1, lake1, sunset1
        $photos = $this->photoRepository->findAllWithUsersFiltered(['username' => 'demo']);

        $this->assertCount(3, $photos);
        foreach ($photos as $photo) {
            $this->assertSame('demo', $photo->getUser()->getUsername());
        }
    }

    public function testFilterByTakenAtReturnsPhotosFromThatDay(): void
    {
        // Fixture: forest1 — takenAt '2024-03-15 07:30:00'
        $photos = $this->photoRepository->findAllWithUsersFiltered(['taken_at' => '2024-03-15']);

        $this->assertCount(1, $photos);
        $this->assertStringContainsString('forest', $photos[0]->getImageUrl());
    }

    public function testFilterByInvalidDateIsIgnored(): void
    {
        // Nieprawidłowa data powinna być zignorowana, nie powodować błędu
        $photos = $this->photoRepository->findAllWithUsersFiltered(['taken_at' => 'not-a-date']);

        $this->assertCount(12, $photos);
    }

    public function testPaginationLimitsResults(): void
    {
        $page1 = $this->photoRepository->findAllWithUsersFiltered([], 1, 6);
        $page2 = $this->photoRepository->findAllWithUsersFiltered([], 2, 6);

        $this->assertCount(6, $page1);
        $this->assertCount(6, $page2);

        // Strony powinny zawierać różne zdjęcia
        $page1Ids = array_map(static fn ($p) => $p->getId(), $page1);
        $page2Ids = array_map(static fn ($p) => $p->getId(), $page2);
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
    }

    public function testCountFilteredReturnsTotal(): void
    {
        $total = $this->photoRepository->countFiltered([]);
        $this->assertSame(12, $total);
    }

    public function testCountFilteredWithFilterReturnsMatchingCount(): void
    {
        $count = $this->photoRepository->countFiltered(['location' => 'Alaska']);
        $this->assertSame(1, $count);
    }
}
