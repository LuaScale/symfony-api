<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class ItemWriteOperationsTest extends ApiTestCase
{
    private const ACCEPT_JSONLD = 'application/ld+json';

    public function testCreateItem(): void
    {
        $client = $this->getTestClientAndReloadFixtures();

        // Get existing shop and category IRIs from fixtures
        $client->request('GET', '/api/shops', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        $shops = $this->assertHydraCollection($this->getJsonResponse($client));
        $shopIri = $this->findInCollection($shops, 'name', 'La Caverne aux Merveilles')['@id'];

        $client->request('GET', '/api/categories', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        $categories = $this->assertHydraCollection($this->getJsonResponse($client));
        $categoryIri = $this->findInCollection($categories, 'slug', 'figurines-vintage')['@id'];

        // Create new item
        $newItem = [
            'name' => 'Astérix Figurine Collection',
            'description' => 'Collection complète de figurines Astérix des années 90',
            'price' => 15000,
            'status' => 'DRAFT',
            'shop' => $shopIri,
            'category' => $categoryIri,
        ];

        $this->jsonLdRequest($client, 'POST', '/api/items', $newItem);

        // Assert resource was created
        $createdIri = $this->assertResourceCreated($client);
        $data = $this->getJsonResponse($client);

        self::assertSame('Astérix Figurine Collection', $data['name']);
        self::assertSame('Collection complète de figurines Astérix des années 90', $data['description']);
        self::assertSame(15000, $data['price']);
        self::assertSame('DRAFT', $data['status']);
        self::assertSame($shopIri, $data['shop']);
        self::assertSame($categoryIri, $data['category']);
        self::assertArrayHasKey('createdAt', $data);

        // Verify the item can be retrieved
        $client->request('GET', $createdIri, server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        self::assertResponseIsSuccessful();
    }

    public function testCreateItemWithMissingRequiredFieldsReturns422(): void
    {
        $client = $this->getTestClientAndReloadFixtures();

        $invalidItem = [
            'description' => 'Item sans nom ni prix',
        ];

        $this->jsonLdRequest($client, 'POST', '/api/items', $invalidItem);

        $this->assertValidationErrors($client, ['name', 'price']);
    }

    public function testCreateItemWithInvalidDataReturns422(): void
    {
        $client = $this->getTestClientAndReloadFixtures();

        // Get shop and category IRIs
        $client->request('GET', '/api/shops', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        $shops = $this->assertHydraCollection($this->getJsonResponse($client));
        $shopIri = $this->findInCollection($shops, 'name', 'La Caverne aux Merveilles')['@id'];

        $client->request('GET', '/api/categories', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        $categories = $this->assertHydraCollection($this->getJsonResponse($client));
        $categoryIri = $this->findInCollection($categories, 'slug', 'figurines-vintage')['@id'];

        $invalidItem = [
            'name' => '', // Empty name should be invalid
            'description' => 'Test',
            'price' => -100, // Negative price should be invalid
            'status' => 'INVALID_STATUS', // Invalid status
            'shop' => $shopIri,
            'category' => $categoryIri,
        ];

        $this->jsonLdRequest($client, 'POST', '/api/items', $invalidItem);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateItemWithPatch(): void
    {
        $client = $this->getTestClientAndReloadFixtures();

        // Get existing item
        $client->request('GET', '/api/items', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        $items = $this->assertHydraCollection($this->getJsonResponse($client));
        $item = $this->findInCollection($items, 'name', 'Goldorak Jumbo Shogun');
        $itemIri = $item['@id'];

        // Partial update with PATCH (only update price and status)
        $patchData = [
            'price' => 28000,
            'status' => 'VALIDATED',
        ];

        $client->request(
            'PATCH',
            $itemIri,
            server: [
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'application/merge-patch+json',
            ],
            content: json_encode($patchData)
        );

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse($client);

        // Only patched fields should change
        self::assertSame($item['name'], $data['name']); // Name unchanged
        self::assertSame(28000, $data['price']); // Price updated
        self::assertSame('VALIDATED', $data['status']); // Status updated
    }

    public function testDeleteItem(): void
    {
        $client = $this->getTestClientAndReloadFixtures();

        // Create a new item to delete
        $client->request('GET', '/api/shops', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        $shops = $this->assertHydraCollection($this->getJsonResponse($client));
        $shopIri = $this->findInCollection($shops, 'name', 'La Caverne aux Merveilles')['@id'];

        $client->request('GET', '/api/categories', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        $categories = $this->assertHydraCollection($this->getJsonResponse($client));
        $categoryIri = $this->findInCollection($categories, 'slug', 'figurines-vintage')['@id'];

        $newItem = [
            'name' => 'Item to Delete',
            'description' => 'This item will be deleted',
            'price' => 5000,
            'status' => 'DRAFT',
            'shop' => $shopIri,
            'category' => $categoryIri,
        ];

        $this->jsonLdRequest($client, 'POST', '/api/items', $newItem);
        $itemIri = $this->assertResourceCreated($client);

        // Delete the item
        $client->request('DELETE', $itemIri);
        $this->assertResourceDeleted();

        // Verify item is gone
        $client->request('GET', $itemIri, server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testCannotDeleteNonExistentItem(): void
    {
        $client = $this->getTestClient();

        $nonExistentId = $this->getNonExistentId();
        $client->request('DELETE', "/api/items/{$nonExistentId}");

        self::assertResponseStatusCodeSame(404);
    }

    public function testCannotUpdateNonExistentItem(): void
    {
        $client = $this->getTestClient();

        $nonExistentId = $this->getNonExistentId();
        $updatedData = [
            'price' => 1000,
            'status' => 'DRAFT',
        ];

        // Use PATCH instead of PUT (API Platform default)
        $client->request(
            'PATCH',
            "/api/items/{$nonExistentId}",
            server: [
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'application/merge-patch+json',
            ],
            content: json_encode($updatedData)
        );

        self::assertResponseStatusCodeSame(404);
    }
}
