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

        self::assertContains($data['@type'] ?? null, ['Collection', 'hydra:Collection']);

        $members = $data['member'] ?? $data['hydra:member'] ?? null;
        self::assertIsArray($members);
        self::assertNotEmpty($members);

        // Find the fixture item by name
        $fixtureItem = null;
        foreach ($members as $item) {
            if (($item['name'] ?? '') === self::FIXTURE_ITEM_NAME) {
                $fixtureItem = $item;
                break;
            }
        }

        self::assertIsArray($fixtureItem, 'Fixture item "' . self::FIXTURE_ITEM_NAME . '" not found in collection');
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

        $collection = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        $members = $collection['member'] ?? $collection['hydra:member'] ?? [];
        self::assertIsArray($members);
        self::assertNotEmpty($members);

        // Find the fixture item by name
        $fixtureItem = null;
        foreach ($members as $item) {
            if (($item['name'] ?? '') === self::FIXTURE_ITEM_NAME) {
                $fixtureItem = $item;
                break;
            }
        }

        self::assertIsArray($fixtureItem, 'Fixture item "' . self::FIXTURE_ITEM_NAME . '" not found in collection');

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
        $client->request('GET', '/api/items/999999999', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);

        self::assertResponseStatusCodeSame(404);
    }
}
