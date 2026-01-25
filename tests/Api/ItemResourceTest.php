<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class ItemResourceTest extends ApiTestCase
{
    private const ACCEPT_JSONLD = 'application/ld+json';
    private const FIXTURE_ITEM_NAME = 'Goldorak Jumbo Shogun';

    public function testGetItemsCollectionReturnsJsonLdCollection(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', '/api/items', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        // Validate Hydra collection structure
        $members = $this->assertHydraCollection($data);
        self::assertNotEmpty($members);

        // Find the fixture item by name
        $fixtureItem = $this->findInCollection($members, 'name', self::FIXTURE_ITEM_NAME);

        self::assertIsString($fixtureItem['name'] ?? null);
        self::assertIsString($fixtureItem['description'] ?? null);

        if (array_key_exists('price', $fixtureItem)) {
            self::assertIsInt($fixtureItem['price']);
        }

        if (array_key_exists('status', $fixtureItem)) {
            self::assertIsString($fixtureItem['status']);
        }

        // createdAt is typically serialized as ISO-8601 string by API Platform
        $createdAt = $fixtureItem['createdAt'] ?? $fixtureItem['created_at'] ?? null;
        if (null !== $createdAt) {
            self::assertIsString($createdAt);
            self::assertNotSame('', trim($createdAt));
        }
    }

    public function testItemHasResolvableShopAndCategoryIris(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', '/api/items', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        // Validate Hydra collection structure
        $members = $this->assertHydraCollection($data);

        // Find the fixture item by name
        $fixtureItem = $this->findInCollection($members, 'name', self::FIXTURE_ITEM_NAME);

        $shopIri = $fixtureItem['shop'] ?? null;
        $categoryIri = $fixtureItem['category'] ?? null;

        self::assertIsString($shopIri);
        self::assertStringStartsWith('/api/', $shopIri);
        self::assertIsString($categoryIri);
        self::assertStringStartsWith('/api/', $categoryIri);

        $client->request('GET', $shopIri, server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        self::assertResponseIsSuccessful();

        $client->request('GET', $categoryIri, server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        self::assertResponseIsSuccessful();
    }

    public function testUnknownItemReturns404(): void
    {
        $client = $this->getTestClient();

        // Test with a non-existent ID to verify proper 404 handling
        // Note: This assumes integer IDs. For UUID-based APIs, this test would need adjustment.
        $nonExistentId = $this->getNonExistentId();
        $client->request('GET', "/api/items/{$nonExistentId}", server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);

        self::assertResponseStatusCodeSame(404);
    }
}
