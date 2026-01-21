<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class CategoryResourceTest extends ApiTestCase
{
    public function testGetCategoriesCollectionContainsFixtureCategory(): void
    {
        $client = $this->createClientAndLoadFixtures();

        $client->request('GET', '/api/categories', ['headers' => ['Accept' => 'application/ld+json']]);
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        $members = $data['member'] ?? $data['hydra:member'] ?? null;

        self::assertIsArray($members);
        self::assertNotEmpty($members);

        $slugs = array_values(array_filter(array_map(static fn ($c) => $c['slug'] ?? null, $members)));
        self::assertContains('figurines-vintage', $slugs);
    }

    public function testCategoryItemHasStringNameAndSlug(): void
    {
        $client = $this->createClientAndLoadFixtures();

        $client->request('GET', '/api/categories', ['headers' => ['Accept' => 'application/ld+json']]);
        self::assertResponseIsSuccessful();

        $collection = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        $members = $collection['member'] ?? $collection['hydra:member'] ?? [];
        self::assertNotEmpty($members);

        $category = $members[0];
        self::assertIsArray($category);
        self::assertIsString($category['name'] ?? null);
        self::assertIsString($category['slug'] ?? null);

        // Ensure the item endpoint is resolvable.
        $id = $category['@id'] ?? null;
        self::assertIsString($id);
        self::assertStringStartsWith('/api/categories/', $id);

        $client->request('GET', $id, ['headers' => ['Accept' => 'application/ld+json']]);
        self::assertResponseIsSuccessful();
    }

    public function testUnknownCategoryReturns404(): void
    {
        $client = $this->createClientAndLoadFixtures();
        $client->request('GET', '/api/categories/999999999', ['headers' => ['Accept' => 'application/ld+json']]);

        self::assertResponseStatusCodeSame(404);
    }
}

