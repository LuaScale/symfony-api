<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class CategoryResourceTest extends ApiTestCase
{
    private const ACCEPT_JSONLD = 'application/ld+json';

    public function testGetCategoriesCollectionContainsFixtureCategory(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', '/api/categories', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        // Validate Hydra collection structure
        $members = $this->assertHydraCollection($data);

        // Verify our fixture category is present
        $fixtureCategory = $this->findInCollection($members, 'slug', 'figurines-vintage');

        self::assertIsString($fixtureCategory['name'] ?? null);
        self::assertSame('Figurines Vintage', $fixtureCategory['name']);
    }

    public function testCategoryItemHasStringNameAndSlug(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', '/api/categories', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        // Validate Hydra collection structure
        $members = $this->assertHydraCollection($data);

        $category = $this->findInCollection($members, 'slug', 'figurines-vintage');

        self::assertIsString($category['name'] ?? null);
        self::assertIsString($category['slug'] ?? null);

        // Ensure the item endpoint is resolvable.
        $id = $category['@id'] ?? null;
        self::assertIsString($id);
        self::assertStringStartsWith('/api/categories/', $id);

        $client->request('GET', $id, server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        self::assertResponseIsSuccessful();
    }

    public function testUnknownCategoryReturns404(): void
    {
        $client = $this->getTestClient();

        // Test with a non-existent ID to verify proper 404 handling
        // Note: This assumes integer IDs. For UUID-based APIs, this test would need adjustment.
        $nonExistentId = $this->getNonExistentId();
        $client->request('GET', "/api/categories/{$nonExistentId}", server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);

        self::assertResponseStatusCodeSame(404);
    }
}
