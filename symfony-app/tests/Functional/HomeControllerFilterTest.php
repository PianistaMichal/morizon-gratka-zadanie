<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\AbstractWebTestCase;

class HomeControllerFilterTest extends AbstractWebTestCase
{
    // --- filter form present ---

    public function testFilterFormIsAlwaysRendered(): void
    {
        $this->client->request('GET', '/');

        $content = $this->getResponseContent();

        $this->assertStringContainsString('name="location"', $content);
        $this->assertStringContainsString('name="camera"', $content);
        $this->assertStringContainsString('name="description"', $content);
        $this->assertStringContainsString('name="taken_at"', $content);
        $this->assertStringContainsString('name="username"', $content);
    }

    // --- filter by location ---

    public function testFilterByLocationReturnsMatchingPhotos(): void
    {
        $this->client->request('GET', '/', ['location' => 'Swiss']);

        $this->assertResponseIsSuccessful();
        $content = $this->getResponseContent();

        $this->assertStringContainsString('Swiss Alps', $content);
        $this->assertStringNotContainsString('Olympic National Park', $content);
    }

    public function testFilterByLocationWithNoMatchShowsEmptyState(): void
    {
        $this->client->request('GET', '/', ['location' => 'nonexistent_xyz_location']);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No photos yet', $this->getResponseContent());
    }

    // --- filter by camera ---

    public function testFilterByCameraReturnsMatchingPhotos(): void
    {
        $this->client->request('GET', '/', ['camera' => 'Canon EOS R5']);

        $this->assertResponseIsSuccessful();
        $content = $this->getResponseContent();

        // Canon EOS R5 photo is at Olympic National Park
        $this->assertStringContainsString('Olympic National Park', $content);
        // Swiss Alps was taken with Sony A7R IV — should not appear
        $this->assertStringNotContainsString('Swiss Alps', $content);
    }

    public function testFilterByCameraPartialMatchReturnsMultiplePhotos(): void
    {
        $this->client->request('GET', '/', ['camera' => 'Nikon']);

        $this->assertResponseIsSuccessful();
        $content = $this->getResponseContent();

        // Nikon D850 (Yellowstone), Nikon Z7 II (Scottish Highlands), Nikon D6 (Alaska)
        $this->assertStringContainsString('Yellowstone', $content);
        $this->assertStringContainsString('Scottish Highlands', $content);
        $this->assertStringContainsString('Alaska', $content);
        // Canon EOS R5 photo should not appear
        $this->assertStringNotContainsString('Olympic National Park', $content);
    }

    // --- filter by description ---

    public function testFilterByDescriptionReturnsMatchingPhotos(): void
    {
        $this->client->request('GET', '/', ['description' => 'salmon']);

        $this->assertResponseIsSuccessful();
        $content = $this->getResponseContent();

        // "salmon" appears only in the Alaska bear photo
        $this->assertStringContainsString('Alaska', $content);
        $this->assertStringNotContainsString('Swiss Alps', $content);
    }

    public function testFilterByDescriptionIsCaseInsensitive(): void
    {
        $this->client->request('GET', '/', ['description' => 'FOREST']);

        $this->assertResponseIsSuccessful();
        $content = $this->getResponseContent();

        // "forest" appears in Olympic National Park description ("ancient forest")
        $this->assertStringContainsString('Olympic National Park', $content);
    }

    // --- filter by taken_at ---

    public function testFilterByTakenAtReturnsMatchingPhotos(): void
    {
        // Olympic National Park photo taken on 2024-03-15
        $this->client->request('GET', '/', ['taken_at' => '2024-03-15']);

        $this->assertResponseIsSuccessful();
        $content = $this->getResponseContent();

        $this->assertStringContainsString('Olympic National Park', $content);
        $this->assertStringNotContainsString('Swiss Alps', $content);
    }

    public function testFilterByTakenAtWithNoMatchShowsEmptyState(): void
    {
        $this->client->request('GET', '/', ['taken_at' => '2000-01-01']);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No photos yet', $this->getResponseContent());
    }

    public function testFilterByTakenAtWithInvalidDateIsIgnored(): void
    {
        // Invalid date should be silently ignored and show all photos
        $this->client->request('GET', '/', ['taken_at' => 'not-a-date']);

        $this->assertResponseIsSuccessful();
        $content = $this->getResponseContent();

        $this->assertStringContainsString('Olympic National Park', $content);
        $this->assertStringContainsString('Swiss Alps', $content);
    }

    // --- filter by username ---

    public function testFilterByUsernameReturnsMatchingPhotos(): void
    {
        // nature_lover (userIndex 1) has: Yellowstone, Alaska, Canadian Rockies
        $this->client->request('GET', '/', ['username' => 'nature_lover']);

        $this->assertResponseIsSuccessful();
        $content = $this->getResponseContent();

        $this->assertStringContainsString('Yellowstone', $content);
        $this->assertStringContainsString('Alaska', $content);
        $this->assertStringContainsString('Canadian Rockies', $content);
        // Swiss Alps belongs to wildlife_pro — should not appear
        $this->assertStringNotContainsString('Swiss Alps', $content);
    }

    public function testFilterByUsernamePartialMatchWorks(): void
    {
        $this->client->request('GET', '/', ['username' => 'landscape']);

        $this->assertResponseIsSuccessful();
        // landscape_dreams (userIndex 3) has: Amazon, Scottish Highlands
        $content = $this->getResponseContent();

        $this->assertStringContainsString('Amazon', $content);
        $this->assertStringContainsString('Scottish Highlands', $content);
    }

    // --- combined filters ---

    public function testCombinedFiltersNarrowResults(): void
    {
        // wildlife_pro (userIndex 2) Canon camera: only Iceland (Canon EOS 5D Mark IV)
        $this->client->request('GET', '/', ['camera' => 'Canon', 'username' => 'wildlife_pro']);

        $this->assertResponseIsSuccessful();
        $content = $this->getResponseContent();

        $this->assertStringContainsString('Iceland', $content);
        // Swiss Alps is wildlife_pro but Sony — should not appear
        $this->assertStringNotContainsString('Swiss Alps', $content);
        // Olympic National Park is Canon but demo user — should not appear
        $this->assertStringNotContainsString('Olympic National Park', $content);
    }

    // --- filter form retains values ---

    public function testFilterFormRetainsValueAfterFiltering(): void
    {
        $this->client->request('GET', '/', ['location' => 'Iceland']);

        $content = $this->getResponseContent();

        $this->assertStringContainsString('value="Iceland"', $content);
    }

    // --- empty filters treated as no filter ---

    public function testEmptyFiltersReturnAllPhotos(): void
    {
        $this->client->request('GET', '/', ['location' => '', 'camera' => '']);

        $this->assertResponseIsSuccessful();
        $content = $this->getResponseContent();

        $this->assertStringContainsString('Olympic National Park', $content);
        $this->assertStringContainsString('Swiss Alps', $content);
    }
}
