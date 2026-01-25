<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class CategoryResourceTest extends ApiTestCase
{
    private const ACCEPT_JSONLD = 'application/ld+json';

    public function testGetCategoriesCollectionContainsFixtureCategory(): void
    {
        $client = $this->createClient();

        $client->request('GET', '/api/categories', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
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
        $client = $this->getTestClient();

        $client->request('GET', '/api/categories', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
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

        $client->request('GET', $id, ['headers' => ['Accept' => self::ACCEPT_JSONLD]]);
        self::assertResponseIsSuccessful();
    }

    public function testUnknownCategoryReturns404(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', '/api/categories/999999999', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);

        self::assertResponseStatusCodeSame(404);
    }
}
