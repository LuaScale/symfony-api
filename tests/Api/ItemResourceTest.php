<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class ItemResourceTest extends ApiTestCase
{
    private const ACCEPT_JSONLD = 'application/ld+json';
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

        $first = $members[0];
        self::assertIsArray($first);
        self::assertIsString($first['name'] ?? null);
        self::assertIsString($first['description'] ?? null);

        if (array_key_exists('price', $first)) {
            self::assertIsInt($first['price']);
        }

        if (array_key_exists('status', $first)) {
            self::assertIsString($first['status']);
        }

        // createdAt is typically serialized as ISO-8601 string by API Platform
        $createdAt = $first['createdAt'] ?? $first['created_at'] ?? null;
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

        $first = $members[0] ?? null;
        self::assertIsArray($first);

        $shopIri = $first['shop'] ?? null;
        $categoryIri = $first['category'] ?? null;

        self::assertIsString($shopIri);
        self::assertStringStartsWith('/api/', $shopIri);
        self::assertIsString($categoryIri);
        self::assertStringStartsWith('/api/', $categoryIri);

        $client->request('GET', $shopIri, ['headers' => ['Accept' => self::ACCEPT_JSONLD]]);
        self::assertResponseIsSuccessful();

        $client->request('GET', $categoryIri, ['headers' => ['Accept' => self::ACCEPT_JSONLD]]);
        self::assertResponseIsSuccessful();
    }

    public function testUnknownItemReturns404(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', '/api/items/999999999', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);

        self::assertResponseStatusCodeSame(404);
    }
}
