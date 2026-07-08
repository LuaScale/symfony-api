<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\DataFixtures\AppFixtures;

final class ShopResourceTest extends ApiTestCase
{
    private const string FIXTURE_SHOP_NAME = 'La Caverne aux Merveilles';

    public function testGetShopsCollectionIsPublicAndContainsFixtureShop(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', self::API_SHOPS, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse($client);
        $members = $this->assertHydraCollection($data);

        $fixtureShop = $this->findInCollection($members, 'name', self::FIXTURE_SHOP_NAME);

        self::assertIsString($fixtureShop['name'] ?? null);
        self::assertIsString($fixtureShop['description'] ?? null);

        $ownerIri = $fixtureShop['owner'] ?? null;
        self::assertIsString($ownerIri);
        self::assertStringStartsWith('/api/users/', $ownerIri);
    }

    public function testShopOwnerIriResolvesWhenAuthenticated(): void
    {
        $client = $this->getTestClientAndReloadFixtures();
        $auth = $this->authHeaders($this->getSellerToken($client));

        $client->request('GET', self::API_SHOPS, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        $members = $this->assertHydraCollection($this->getJsonResponse($client));
        $ownerIri = $this->findInCollection($members, 'name', self::FIXTURE_SHOP_NAME)['owner'];

        $client->request('GET', $ownerIri, server: array_merge(['HTTP_ACCEPT' => self::CT_JSONLD], $auth));
        self::assertResponseIsSuccessful();
    }

    public function testUnknownShopReturns404(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', self::API_SHOPS.'/'.$this->getNonExistentId(), server: ['HTTP_ACCEPT' => self::CT_JSONLD]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateShopRequiresAuthentication(): void
    {
        $client = $this->getTestClient();

        $this->jsonLdRequest($client, 'POST', self::API_SHOPS, ['name' => 'Unauthorized Shop']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testDeleteShopOwnedByAnotherUserReturns403(): void
    {
        $client = $this->getTestClientAndReloadFixtures();

        // Get seller's shop IRI
        $client->request('GET', self::API_SHOPS, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        $members = $this->assertHydraCollection($this->getJsonResponse($client));
        $shopIri = $this->findInCollection($members, 'name', self::FIXTURE_SHOP_NAME)['@id'];

        // Attempt delete as the buyer (who does not own this shop)
        $buyerToken = $this->getJwtToken($client, AppFixtures::BUYER_EMAIL, AppFixtures::BUYER_PASSWORD);
        $client->request('DELETE', $shopIri, server: $this->authHeaders($buyerToken));

        self::assertResponseStatusCodeSame(403);
    }
}
