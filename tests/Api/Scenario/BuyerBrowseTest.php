<?php

declare(strict_types=1);

namespace App\Tests\Api\Scenario;

use App\Tests\Api\ApiTestCase;

/**
 * Buyer browsing scenarios: filters, sorting, search — all unauthenticated.
 */
final class BuyerBrowseTest extends ApiTestCase
{
    private const JSONLD = 'application/ld+json';

    public function testBuyerBrowseItemsByCategory(): void
    {
        $client = $this->getTestClient();

        // Resolve "Figurines Vintage" IRI
        $client->request('GET', '/api/categories', server: ['HTTP_ACCEPT' => self::JSONLD]);
        $categories  = $this->assertHydraCollection($this->getJsonResponse($client));
        $figurinesIri = $this->findInCollection($categories, 'slug', 'figurines-vintage')['@id'];
        $figurinesId  = basename($figurinesIri);

        // Filter items by category
        $client->request('GET', "/api/items?category=/api/categories/{$figurinesId}", server: ['HTTP_ACCEPT' => self::JSONLD]);
        self::assertResponseIsSuccessful();
        $items = $this->assertHydraCollection($this->getJsonResponse($client));

        self::assertNotEmpty($items, 'Expected at least one figurine item from fixtures');

        foreach ($items as $item) {
            self::assertSame("/api/categories/{$figurinesId}", $item['category']);
        }
    }

    public function testBuyerFilterItemsByStatus(): void
    {
        $client = $this->getTestClient();

        foreach (['VALIDATED', 'DRAFT', 'REJECTED'] as $status) {
            $client->request('GET', "/api/items?status={$status}", server: ['HTTP_ACCEPT' => self::JSONLD]);
            self::assertResponseIsSuccessful();
            $items = $this->assertHydraCollection($this->getJsonResponse($client));

            foreach ($items as $item) {
                self::assertSame($status, $item['status'], "Item returned for status={$status} has wrong status");
            }
        }
    }

    public function testBuyerFilterByPriceRange(): void
    {
        $client = $this->getTestClient();

        $min = 5000;
        $max = 20000;
        $client->request('GET', "/api/items?price[gte]={$min}&price[lte]={$max}", server: ['HTTP_ACCEPT' => self::JSONLD]);
        self::assertResponseIsSuccessful();
        $items = $this->assertHydraCollection($this->getJsonResponse($client));

        self::assertNotEmpty($items, 'Expected items within price range from fixtures');

        foreach ($items as $item) {
            self::assertGreaterThanOrEqual($min, $item['price'], "Item price {$item['price']} is below minimum {$min}");
            self::assertLessThanOrEqual($max, $item['price'], "Item price {$item['price']} exceeds maximum {$max}");
        }
    }

    public function testBuyerSortItemsByPriceAsc(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', '/api/items?order[price]=asc', server: ['HTTP_ACCEPT' => self::JSONLD]);
        self::assertResponseIsSuccessful();
        $items = $this->assertHydraCollection($this->getJsonResponse($client));

        self::assertNotEmpty($items);

        $prices = array_column($items, 'price');
        $sorted = $prices;
        sort($sorted);
        self::assertSame($sorted, $prices, 'Items are not sorted by price ascending');
    }

    public function testBuyerSortItemsByPriceDesc(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', '/api/items?order[price]=desc', server: ['HTTP_ACCEPT' => self::JSONLD]);
        self::assertResponseIsSuccessful();
        $items = $this->assertHydraCollection($this->getJsonResponse($client));

        self::assertNotEmpty($items);

        $prices = array_column($items, 'price');
        $sorted = $prices;
        rsort($sorted);
        self::assertSame($sorted, $prices, 'Items are not sorted by price descending');
    }

    public function testBuyerSortItemsByCreatedAtDesc(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', '/api/items?order[createdAt]=desc', server: ['HTTP_ACCEPT' => self::JSONLD]);
        self::assertResponseIsSuccessful();
        $items = $this->assertHydraCollection($this->getJsonResponse($client));

        self::assertNotEmpty($items);

        $dates = array_column($items, 'createdAt');
        $sorted = $dates;
        rsort($sorted);
        self::assertSame($sorted, $dates, 'Items are not sorted by createdAt descending');
    }

    public function testBuyerSearchItemsByName(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', '/api/items?name=Goldorak', server: ['HTTP_ACCEPT' => self::JSONLD]);
        self::assertResponseIsSuccessful();
        $items = $this->assertHydraCollection($this->getJsonResponse($client));

        self::assertNotEmpty($items, 'Expected "Goldorak" item from fixtures to appear in name search');

        foreach ($items as $item) {
            self::assertStringContainsStringIgnoringCase('Goldorak', $item['name']);
        }
    }

    public function testBuyerCombinesStatusAndPriceFilters(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', '/api/items?status=VALIDATED&order[price]=asc', server: ['HTTP_ACCEPT' => self::JSONLD]);
        self::assertResponseIsSuccessful();
        $items = $this->assertHydraCollection($this->getJsonResponse($client));

        // All returned items must be VALIDATED and sorted by price ascending
        $prices = [];
        foreach ($items as $item) {
            self::assertSame('VALIDATED', $item['status']);
            $prices[] = $item['price'];
        }

        $sorted = $prices;
        sort($sorted);
        self::assertSame($sorted, $prices);
    }

    public function testBuyerViewsShopItemsViaFilter(): void
    {
        $client = $this->getTestClient();

        // Get a shop
        $client->request('GET', '/api/shops', server: ['HTTP_ACCEPT' => self::JSONLD]);
        $shops   = $this->assertHydraCollection($this->getJsonResponse($client));
        $shopIri = $this->findInCollection($shops, 'name', 'La Caverne aux Merveilles')['@id'];
        $shopId  = basename($shopIri);

        // Filter items by that shop
        $client->request('GET', "/api/items?shop=/api/shops/{$shopId}", server: ['HTTP_ACCEPT' => self::JSONLD]);
        self::assertResponseIsSuccessful();
        $items = $this->assertHydraCollection($this->getJsonResponse($client));

        self::assertNotEmpty($items, 'Expected items from "La Caverne aux Merveilles"');

        foreach ($items as $item) {
            self::assertSame("/api/shops/{$shopId}", $item['shop']);
        }
    }

    public function testBuyerCannotWriteWithoutAuthentication(): void
    {
        $client = $this->getTestClient();

        // POST /api/items without auth → 401
        $this->jsonLdRequest($client, 'POST', '/api/items', [
            'name' => 'Unauthorized', 'description' => 'x', 'price' => 100, 'status' => 'DRAFT',
        ]);
        self::assertResponseStatusCodeSame(401);

        // POST /api/shops without auth → 401
        $this->jsonLdRequest($client, 'POST', '/api/shops', ['name' => 'Unauthorized Shop']);
        self::assertResponseStatusCodeSame(401);
    }
}
