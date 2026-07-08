<?php

declare(strict_types=1);

namespace App\Tests\Api\Scenario;

use App\Tests\Api\ApiTestCase;

/**
 * Full seller lifecycle: register → login → create shop → manage items → tear down.
 */
final class SellerJourneyTest extends ApiTestCase
{
    public function testSellerRegistersAndCreatesShopWithItems(): void
    {
        $client = $this->getTestClientAndReloadFixtures();

        // 1. Register as a new seller
        $uniqueEmail = 'seller_'.uniqid().'@collector.shop';

        $this->jsonLdRequest($client, 'POST', self::API_USERS, [
            'email' => $uniqueEmail,
            'pseudo' => 'NewSeller',
            'password' => 'password123',
        ]);
        self::assertResponseStatusCodeSame(201);
        $userData = $this->getJsonResponse($client);
        self::assertSame($uniqueEmail, $userData['email']);
        self::assertArrayNotHasKey('password', $userData);

        // 2. Login → JWT
        $token = $this->getJwtToken($client, $uniqueEmail, 'password123');
        $auth  = $this->authHeaders($token);

        // 3. Create a shop
        $shopIri = $this->createShopViaApi($client, $token, 'Ma Boutique E2E', 'Boutique de test');

        // 4. Shop is visible in the public listing
        $client->request('GET', self::API_SHOPS, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        $shops = $this->assertHydraCollection($this->getJsonResponse($client));
        $createdShop = $this->findInCollection($shops, 'name', 'Ma Boutique E2E');
        self::assertSame('Boutique de test', $createdShop['description']);

        // 5. Get a category IRI for the items
        $categoryIri = $this->getFirstCategoryIri($client);

        // 6. Create two items (DRAFT)
        $item1Iri = $this->createItemViaApi($client, $token, 'Objet A', $shopIri, $categoryIri, 5000, 'DRAFT');
        $item2Iri = $this->createItemViaApi($client, $token, 'Objet B', $shopIri, $categoryIri, 8000, 'DRAFT');

        // 7. Update first item: DRAFT → VALIDATED, new price
        $client->request('PATCH', $item1Iri, server: array_merge([
            'HTTP_ACCEPT' => self::CT_JSONLD,
            'CONTENT_TYPE' => self::CT_MERGE_PATCH,
        ], $auth), content: json_encode(['status' => 'VALIDATED', 'price' => 5500]));
        self::assertResponseIsSuccessful();
        $updated = $this->getJsonResponse($client);
        self::assertSame('VALIDATED', $updated['status']);
        self::assertSame(5500, $updated['price']);

        // 8. Both items appear when filtered by shop
        $shopId = basename($shopIri);
        $client->request('GET', self::API_ITEMS.'?shop='.self::API_SHOPS."/{$shopId}", server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        $items = $this->assertHydraCollection($this->getJsonResponse($client));
        self::assertCount(2, $items);

        // 9. Delete second item
        $client->request('DELETE', $item2Iri, server: $auth);
        $this->assertResourceDeleted();

        // 10. Shop still exists, now with one item
        $client->request('GET', $shopIri, server: array_merge(['HTTP_ACCEPT' => self::CT_JSONLD], $auth));
        self::assertResponseIsSuccessful();

        $client->request('GET', self::API_ITEMS.'?shop='.self::API_SHOPS."/{$shopId}", server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        $items = $this->assertHydraCollection($this->getJsonResponse($client));
        self::assertCount(1, $items);
        self::assertSame('Objet A', $items[0]['name']);

        // 11. Update shop name
        $client->request('PATCH', $shopIri, server: array_merge([
            'HTTP_ACCEPT' => self::CT_JSONLD,
            'CONTENT_TYPE' => self::CT_MERGE_PATCH,
        ], $auth), content: json_encode(['name' => 'Ma Boutique Renommée']));
        self::assertResponseIsSuccessful();
        self::assertSame('Ma Boutique Renommée', $this->getJsonResponse($client)['name']);

        // 12. Delete remaining item then shop
        $client->request('DELETE', $item1Iri, server: $auth);
        $this->assertResourceDeleted();

        $client->request('DELETE', $shopIri, server: $auth);
        $this->assertResourceDeleted();

        // 13. Shop is gone
        $client->request('GET', $shopIri, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testSellerCannotCreateItemInAnotherSellersShop(): void
    {
        $client = $this->getTestClientAndReloadFixtures();

        // Buyer owns "Comic Hub Paris"
        $buyerToken = $this->getJwtToken($client, 'acheteur@collector.shop', 'buyer-fixture-password');
        $auth = $this->authHeaders($buyerToken);

        // Get seller's shop IRI
        $client->request('GET', self::API_SHOPS, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        $shops = $this->assertHydraCollection($this->getJsonResponse($client));
        $sellerShopIri = $this->findInCollection($shops, 'name', 'La Caverne aux Merveilles')['@id'];

        $categoryIri = $this->getFirstCategoryIri($client);

        // Buyer tries to create item in seller's shop → 403 (securityPostDenormalize)
        $this->jsonLdRequest($client, 'POST', self::API_ITEMS, [
            'name' => 'Intrusion Item',
            'description' => 'Should not be allowed',
            'price' => 1000,
            'status' => 'DRAFT',
            'shop' => $sellerShopIri,
            'category' => $categoryIri,
        ], $auth);

        self::assertResponseStatusCodeSame(403);
    }

    public function testSellerUpdatesItemStatusTransitions(): void
    {
        $client = $this->getTestClientAndReloadFixtures();
        $token  = $this->getSellerToken($client);

        // Existing DRAFT item from fixtures
        $client->request('GET', self::API_ITEMS, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        $items   = $this->assertHydraCollection($this->getJsonResponse($client));
        $draftItem = $this->findInCollection($items, 'name', 'Game Boy Color – Violet');
        $itemIri   = $draftItem['@id'];
        self::assertSame('DRAFT', $draftItem['status']);

        $auth = $this->authHeaders($token);
        $patch = static fn(array $data) => json_encode($data);

        // DRAFT → VALIDATED
        $client->request('PATCH', $itemIri, server: array_merge([
            'HTTP_ACCEPT'  => self::CT_JSONLD,
            'CONTENT_TYPE' => self::CT_MERGE_PATCH,
        ], $auth), content: $patch(['status' => 'VALIDATED']));
        self::assertResponseIsSuccessful();
        self::assertSame('VALIDATED', $this->getJsonResponse($client)['status']);

        // VALIDATED → REJECTED
        $client->request('PATCH', $itemIri, server: array_merge([
            'HTTP_ACCEPT'  => self::CT_JSONLD,
            'CONTENT_TYPE' => self::CT_MERGE_PATCH,
        ], $auth), content: $patch(['status' => 'REJECTED']));
        self::assertResponseIsSuccessful();
        self::assertSame('REJECTED', $this->getJsonResponse($client)['status']);

        // REJECTED → DRAFT (back to draft is allowed)
        $client->request('PATCH', $itemIri, server: array_merge([
            'HTTP_ACCEPT'  => self::CT_JSONLD,
            'CONTENT_TYPE' => self::CT_MERGE_PATCH,
        ], $auth), content: $patch(['status' => 'DRAFT']));
        self::assertResponseIsSuccessful();
        self::assertSame('DRAFT', $this->getJsonResponse($client)['status']);
    }
}
