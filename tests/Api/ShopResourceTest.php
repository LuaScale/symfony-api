<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class ShopResourceTest extends ApiTestCase
{
    private const ACCEPT_JSONLD = 'application/ld+json';
    private const FIXTURE_SHOP_NAME = 'La Caverne aux Merveilles';

    public function testGetShopsCollectionContainsFixtureShopAndOwnerIriResolves(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', '/api/shops', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        // Validate Hydra collection structure
        $members = $this->assertHydraCollection($data);

        // Find the fixture shop by name
        $fixtureShop = $this->findInCollection($members, 'name', self::FIXTURE_SHOP_NAME);

        self::assertIsString($fixtureShop['name'] ?? null);
        self::assertIsString($fixtureShop['description'] ?? null);

        $ownerIri = $fixtureShop['owner'] ?? null;
        self::assertIsString($ownerIri);
        self::assertStringStartsWith('/api/users/', $ownerIri);

        $client->request('GET', $ownerIri, server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        self::assertResponseIsSuccessful();
    }

    public function testUnknownShopReturns404(): void
    {
        $client = $this->getTestClient();

        // Test with a non-existent ID to verify proper 404 handling
        // Note: This assumes integer IDs. For UUID-based APIs, this test would need adjustment.
        $nonExistentId = $this->getNonExistentId();
        $client->request('GET', "/api/shops/{$nonExistentId}", server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);

        self::assertResponseStatusCodeSame(404);
    }
}
