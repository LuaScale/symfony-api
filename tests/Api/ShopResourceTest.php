<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class ShopResourceTest extends ApiTestCase
{
    public function testGetShopsCollectionContainsFixtureShopAndOwnerIriResolves(): void
    {
        $client = $this->createClientAndLoadFixtures();

        $client->request('GET', '/api/shops', ['headers' => ['Accept' => 'application/ld+json']]);
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        $members = $data['member'] ?? $data['hydra:member'] ?? null;

        self::assertIsArray($members);
        self::assertNotEmpty($members);

        $shop = $members[0];
        self::assertIsArray($shop);

        self::assertIsString($shop['name'] ?? null);
        self::assertIsString($shop['description'] ?? null);

        $ownerIri = $shop['owner'] ?? null;
        self::assertIsString($ownerIri);
        self::assertStringStartsWith('/api/users/', $ownerIri);

        $client->request('GET', $ownerIri, ['headers' => ['Accept' => 'application/ld+json']]);
        self::assertResponseIsSuccessful();
    }

    public function testUnknownShopReturns404(): void
    {
        $client = $this->createClientAndLoadFixtures();
        $client->request('GET', '/api/shops/999999999', ['headers' => ['Accept' => 'application/ld+json']]);

        self::assertResponseStatusCodeSame(404);
    }
}

