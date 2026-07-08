<?php

declare(strict_types=1);

namespace App\Tests\Api\Scenario;

use App\Tests\Api\ApiTestCase;

/**
 * Shop CRUD operations — entirely missing from the pre-existing test suite.
 */
final class ShopManagementTest extends ApiTestCase
{
    private const JSONLD = 'application/ld+json';

    public function testCreateShopAsAuthenticatedUser(): void
    {
        $client = $this->getTestClientAndReloadFixtures();
        $token  = $this->getSellerToken($client);

        $shopIri = $this->createShopViaApi($client, $token, 'Nouvelle Boutique Test', 'Description de la boutique');

        self::assertStringStartsWith('/api/shops/', $shopIri);

        // Shop visible in public listing
        $client->request('GET', '/api/shops', server: ['HTTP_ACCEPT' => self::JSONLD]);
        $shops = $this->assertHydraCollection($this->getJsonResponse($client));
        $created = $this->findInCollection($shops, 'name', 'Nouvelle Boutique Test');

        self::assertSame('Description de la boutique', $created['description']);
        self::assertStringStartsWith('/api/users/', $created['owner']);
    }

    public function testUpdateShopAsOwner(): void
    {
        $client = $this->getTestClientAndReloadFixtures();
        $token  = $this->getSellerToken($client);
        $auth   = $this->authHeaders($token);

        // Get seller's existing shop
        $client->request('GET', '/api/shops', server: ['HTTP_ACCEPT' => self::JSONLD]);
        $shops   = $this->assertHydraCollection($this->getJsonResponse($client));
        $shopIri = $this->findInCollection($shops, 'name', 'La Caverne aux Merveilles')['@id'];

        $client->request('PATCH', $shopIri, server: array_merge([
            'HTTP_ACCEPT'  => self::JSONLD,
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], $auth), content: json_encode([
            'name'        => 'La Grotte aux Merveilles',
            'description' => 'Nouvelle description',
        ]));

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse($client);
        self::assertSame('La Grotte aux Merveilles', $data['name']);
        self::assertSame('Nouvelle description', $data['description']);
    }

    public function testDeleteShopAsOwner(): void
    {
        $client = $this->getTestClientAndReloadFixtures();
        $token  = $this->getSellerToken($client);

        // Create a disposable shop
        $shopIri = $this->createShopViaApi($client, $token, 'Boutique à supprimer');

        $client->request('DELETE', $shopIri, server: $this->authHeaders($token));
        $this->assertResourceDeleted();

        $client->request('GET', $shopIri, server: ['HTTP_ACCEPT' => self::JSONLD]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testCannotUpdateAnotherUsersShop(): void
    {
        $client      = $this->getTestClientAndReloadFixtures();
        $buyerToken  = $this->getJwtToken($client, 'acheteur@collector.shop', 'buyer-fixture-password');

        $client->request('GET', '/api/shops', server: ['HTTP_ACCEPT' => self::JSONLD]);
        $shops   = $this->assertHydraCollection($this->getJsonResponse($client));
        $shopIri = $this->findInCollection($shops, 'name', 'La Caverne aux Merveilles')['@id'];

        $client->request('PATCH', $shopIri, server: array_merge([
            'HTTP_ACCEPT'  => self::JSONLD,
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], $this->authHeaders($buyerToken)), content: json_encode(['name' => 'Prise de contrôle']));

        self::assertResponseStatusCodeSame(403);
    }

    public function testShopItemsPopulatedAfterItemCreation(): void
    {
        $client = $this->getTestClientAndReloadFixtures();
        $token  = $this->getSellerToken($client);
        $auth   = $this->authHeaders($token);

        // Create a fresh shop
        $shopIri     = $this->createShopViaApi($client, $token, 'Boutique avec Items');
        $categoryIri = $this->getFirstCategoryIri($client);

        // Add two items
        $this->createItemViaApi($client, $token, 'Item 1', $shopIri, $categoryIri, 1000);
        $this->createItemViaApi($client, $token, 'Item 2', $shopIri, $categoryIri, 2000);

        // The shop's `items` field should list them
        $client->request('GET', $shopIri, server: array_merge(['HTTP_ACCEPT' => self::JSONLD], $auth));
        self::assertResponseIsSuccessful();
        $shop = $this->getJsonResponse($client);

        self::assertIsArray($shop['items']);
        self::assertCount(2, $shop['items']);
    }

    public function testShopCreationRequiresAuthentication(): void
    {
        $client = $this->getTestClient();

        $this->jsonLdRequest($client, 'POST', '/api/shops', ['name' => 'Boutique anonyme']);

        self::assertResponseStatusCodeSame(401);
    }
}
