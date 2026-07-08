<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class ItemWriteOperationsTest extends ApiTestCase
{
    private const ACCEPT_JSONLD = 'application/ld+json';

    public function testCreateItem(): void
    {
        $client = $this->getTestClientAndReloadFixtures();
        $token = $this->getSellerToken($client);
        $auth = $this->authHeaders($token);

        $client->request('GET', '/api/shops', server: array_merge(['HTTP_ACCEPT' => self::ACCEPT_JSONLD], $auth));
        $shops = $this->assertHydraCollection($this->getJsonResponse($client));
        $shopIri = $this->findInCollection($shops, 'name', 'La Caverne aux Merveilles')['@id'];

        $client->request('GET', '/api/categories', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        $categories = $this->assertHydraCollection($this->getJsonResponse($client));
        $categoryIri = $this->findInCollection($categories, 'slug', 'figurines-vintage')['@id'];

        $newItem = [
            'name' => 'Astérix Figurine Collection',
            'description' => 'Collection complète de figurines Astérix des années 90',
            'price' => 15000,
            'status' => 'DRAFT',
            'shop' => $shopIri,
            'category' => $categoryIri,
        ];

        $this->jsonLdRequest($client, 'POST', '/api/items', $newItem, $auth);

        $createdIri = $this->assertResourceCreated($client);
        $data = $this->getJsonResponse($client);

        self::assertSame('Astérix Figurine Collection', $data['name']);
        self::assertSame('Collection complète de figurines Astérix des années 90', $data['description']);
        self::assertSame(15000, $data['price']);
        self::assertSame('DRAFT', $data['status']);
        self::assertSame($shopIri, $data['shop']);
        self::assertSame($categoryIri, $data['category']);
        self::assertArrayHasKey('createdAt', $data);

        $client->request('GET', $createdIri, server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        self::assertResponseIsSuccessful();
    }

    public function testCreateItemWithMissingRequiredFieldsReturns422(): void
    {
        $client = $this->getTestClientAndReloadFixtures();
        $auth = $this->authHeaders($this->getSellerToken($client));

        $this->jsonLdRequest($client, 'POST', '/api/items', ['description' => 'Item sans nom ni prix'], $auth);

        $this->assertValidationErrors($client, ['name', 'price']);
    }

    public function testCreateItemWithInvalidDataReturns422(): void
    {
        $client = $this->getTestClientAndReloadFixtures();
        $auth = $this->authHeaders($this->getSellerToken($client));

        $client->request('GET', '/api/shops', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        $shops = $this->assertHydraCollection($this->getJsonResponse($client));
        $shopIri = $this->findInCollection($shops, 'name', 'La Caverne aux Merveilles')['@id'];

        $client->request('GET', '/api/categories', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        $categories = $this->assertHydraCollection($this->getJsonResponse($client));
        $categoryIri = $this->findInCollection($categories, 'slug', 'figurines-vintage')['@id'];

        $this->jsonLdRequest($client, 'POST', '/api/items', [
            'name' => '',
            'description' => 'Test',
            'price' => -100,
            'status' => 'INVALID_STATUS',
            'shop' => $shopIri,
            'category' => $categoryIri,
        ], $auth);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateItemWithPatch(): void
    {
        $client = $this->getTestClientAndReloadFixtures();
        $auth = $this->authHeaders($this->getSellerToken($client));

        $client->request('GET', '/api/items', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        $items = $this->assertHydraCollection($this->getJsonResponse($client));
        $item = $this->findInCollection($items, 'name', 'Goldorak Jumbo Shogun');
        $itemIri = $item['@id'];

        $client->request(
            'PATCH',
            $itemIri,
            server: array_merge([
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'application/merge-patch+json',
            ], $auth),
            content: json_encode(['price' => 28000, 'status' => 'VALIDATED'])
        );

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse($client);

        self::assertSame($item['name'], $data['name']);
        self::assertSame(28000, $data['price']);
        self::assertSame('VALIDATED', $data['status']);
    }

    public function testDeleteItem(): void
    {
        $client = $this->getTestClientAndReloadFixtures();
        $auth = $this->authHeaders($this->getSellerToken($client));

        $client->request('GET', '/api/shops', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        $shops = $this->assertHydraCollection($this->getJsonResponse($client));
        $shopIri = $this->findInCollection($shops, 'name', 'La Caverne aux Merveilles')['@id'];

        $client->request('GET', '/api/categories', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        $categories = $this->assertHydraCollection($this->getJsonResponse($client));
        $categoryIri = $this->findInCollection($categories, 'slug', 'figurines-vintage')['@id'];

        $this->jsonLdRequest($client, 'POST', '/api/items', [
            'name' => 'Item to Delete',
            'description' => 'This item will be deleted',
            'price' => 5000,
            'status' => 'DRAFT',
            'shop' => $shopIri,
            'category' => $categoryIri,
        ], $auth);
        $itemIri = $this->assertResourceCreated($client);

        $client->request('DELETE', $itemIri, server: $auth);
        $this->assertResourceDeleted();

        $client->request('GET', $itemIri, server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testCannotDeleteNonExistentItem(): void
    {
        $client = $this->getTestClient();
        $auth = $this->authHeaders($this->getSellerToken($client));

        $client->request('DELETE', '/api/items/'.$this->getNonExistentId(), server: $auth);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCannotUpdateNonExistentItem(): void
    {
        $client = $this->getTestClient();
        $auth = $this->authHeaders($this->getSellerToken($client));

        $client->request(
            'PATCH',
            '/api/items/'.$this->getNonExistentId(),
            server: array_merge([
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'application/merge-patch+json',
            ], $auth),
            content: json_encode(['price' => 1000, 'status' => 'DRAFT'])
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnauthenticatedCreateReturns401(): void
    {
        $client = $this->getTestClient();

        $this->jsonLdRequest($client, 'POST', '/api/items', [
            'name' => 'Unauthorized Item',
            'description' => 'Should not be created',
            'price' => 1000,
            'status' => 'DRAFT',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testDeleteItemOwnedByAnotherUserReturns403(): void
    {
        $client = $this->getTestClientAndReloadFixtures();

        // Fetch an item owned by the seller
        $client->request('GET', '/api/items', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        $items = $this->assertHydraCollection($this->getJsonResponse($client));
        $sellerItemIri = $this->findInCollection($items, 'name', 'Goldorak Jumbo Shogun')['@id'];

        // Try to delete it as the buyer (different user)
        $buyerToken = $this->getJwtToken($client, 'acheteur@collector.shop', 'buyer-fixture-password');
        $client->request('DELETE', $sellerItemIri, server: $this->authHeaders($buyerToken));

        self::assertResponseStatusCodeSame(403);
    }
}
