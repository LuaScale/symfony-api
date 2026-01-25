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
        $members = $data['member'] ?? $data['hydra:member'] ?? null;

        self::assertIsArray($members);
        self::assertNotEmpty($members);

        // Find the fixture shop by name
        $fixtureShop = null;
        foreach ($members as $shop) {
            if (($shop['name'] ?? '') === self::FIXTURE_SHOP_NAME) {
                $fixtureShop = $shop;
                break;
            }
        }

        self::assertIsArray($fixtureShop, 'Fixture shop "' . self::FIXTURE_SHOP_NAME . '" not found in collection');
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
        $client->request('GET', '/api/shops/999999999', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);

        self::assertResponseStatusCodeSame(404);
    }
}
