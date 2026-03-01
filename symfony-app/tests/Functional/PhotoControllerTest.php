<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Photo;
use App\Tests\AbstractWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class PhotoControllerTest extends AbstractWebTestCase
{
    public function testLikePhotoRequiresLogin(): void
    {
        $photo = $this->getFirstPhoto();

        $this->client->request('GET', "/photo/{$photo->getId()}/like");

        $this->assertResponseRedirects('/');
    }

    public function testLikePhotoWhenNotLoggedInShowsErrorFlash(): void
    {
        $photo = $this->getFirstPhoto();

        $this->client->request('GET', "/photo/{$photo->getId()}/like");
        $this->client->followRedirect();

        $this->assertStringContainsString('You must be logged in', $this->getResponseContent());
    }

    public function testLikePhotoWhenLoggedInRedirectsToHome(): void
    {
        $this->loginAs('demo');
        $photo = $this->getFirstPhoto();

        $this->client->request('GET', "/photo/{$photo->getId()}/like");

        $this->assertResponseRedirects('/');
    }

    public function testLikePhotoShowsSuccessFlash(): void
    {
        $this->loginAs('demo');
        $photo = $this->getFirstPhoto();

        $this->client->request('GET', "/photo/{$photo->getId()}/like");
        $this->client->followRedirect();

        $this->assertStringContainsString('Photo liked!', $this->getResponseContent());
    }

    public function testUnlikePhotoShowsInfoFlash(): void
    {
        $this->loginAs('demo');
        $photo = $this->getFirstPhoto();
        $photoId = $photo->getId();

        // Pierwsze kliknięcie: like
        $this->client->request('GET', "/photo/{$photoId}/like");
        // Drugie kliknięcie: unlike
        $this->client->request('GET', "/photo/{$photoId}/like");
        $this->client->followRedirect();

        $this->assertStringContainsString('Photo unliked!', $this->getResponseContent());
    }

    public function testLikeIncreasesCounter(): void
    {
        $this->loginAs('demo');
        $photo = $this->getFirstPhoto();
        $photoId = $photo->getId();
        $initialCount = $photo->getLikeCounter();

        $this->client->request('GET', "/photo/{$photoId}/like");

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $updatedPhoto = $em->getRepository(Photo::class)->find($photoId);
        $this->assertNotNull($updatedPhoto);

        $this->assertSame($initialCount + 1, $updatedPhoto->getLikeCounter());
    }

    public function testUnlikeDecreasesCounter(): void
    {
        $this->loginAs('demo');
        $photo = $this->getFirstPhoto();
        $photoId = $photo->getId();

        // Like
        $this->client->request('GET', "/photo/{$photoId}/like");
        // Unlike
        $this->client->request('GET', "/photo/{$photoId}/like");

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $updatedPhoto = $em->getRepository(Photo::class)->find($photoId);
        $this->assertNotNull($updatedPhoto);

        $this->assertSame(0, $updatedPhoto->getLikeCounter());
    }

    public function testMultipleUsersCanLikeSamePhoto(): void
    {
        $photo = $this->getFirstPhoto();
        $photoId = $photo->getId();

        $this->loginAs('demo');
        $this->client->request('GET', "/photo/{$photoId}/like");

        $this->loginAs('nature_lover');
        $this->client->request('GET', "/photo/{$photoId}/like");

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $updatedPhoto = $em->getRepository(Photo::class)->find($photoId);
        $this->assertNotNull($updatedPhoto);

        $this->assertSame(2, $updatedPhoto->getLikeCounter());
    }
}
