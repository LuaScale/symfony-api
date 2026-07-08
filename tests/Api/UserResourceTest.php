<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\DataFixtures\AppFixtures;

final class UserResourceTest extends ApiTestCase
{
    private const string TEST_EMAIL = 'nouveau@collector.shop';

    public function testGetUsersCollectionIsPublicAndContainsFixtureUser(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', self::API_USERS, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse($client);
        $members = $this->assertHydraCollection($data);

        $fixtureUser = $this->findInCollection($members, 'email', AppFixtures::SELLER_EMAIL);

        self::assertIsString($fixtureUser['email'] ?? null);
        self::assertIsString($fixtureUser['pseudo'] ?? null);
        self::assertArrayNotHasKey('password', $fixtureUser, 'password must never be exposed in API responses');

        if (array_key_exists('isVerified', $fixtureUser)) {
            self::assertIsBool($fixtureUser['isVerified']);
        }
    }

    public function testGetSingleUserRequiresAuthentication(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', self::API_USERS, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        $members = $this->assertHydraCollection($this->getJsonResponse($client));
        $userIri = $this->findInCollection($members, 'email', AppFixtures::SELLER_EMAIL)['@id'];

        // Unauthenticated → 401
        $client->request('GET', $userIri, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        self::assertResponseStatusCodeSame(401);

        // Authenticated → 200
        $token = $this->getSellerToken($client);
        $client->request('GET', $userIri, server: array_merge(['HTTP_ACCEPT' => self::CT_JSONLD], $this->authHeaders($token)));
        self::assertResponseIsSuccessful();
    }

    public function testUnknownUserReturns404(): void
    {
        $client = $this->getTestClient();
        $auth = $this->authHeaders($this->getSellerToken($client));

        $client->request('GET', self::API_USERS.'/'.$this->getNonExistentId(), server: array_merge(['HTTP_ACCEPT' => self::CT_JSONLD], $auth));

        self::assertResponseStatusCodeSame(404);
    }

    public function testRegisterNewUser(): void
    {
        $client = $this->getTestClientAndReloadFixtures();

        $this->jsonLdRequest($client, 'POST', self::API_USERS, [
            'email' => self::TEST_EMAIL,
            'pseudo' => 'NouveauVendeur',
            'password' => 'motdepasse123',
        ]);

        $this->assertResourceCreated($client);
        $data = $this->getJsonResponse($client);

        self::assertSame(self::TEST_EMAIL, $data['email']);
        self::assertSame('NouveauVendeur', $data['pseudo']);
        self::assertArrayNotHasKey('password', $data, 'password must never be exposed');

        // New user can log in with the plain-text password used at registration
        $token = $this->getJwtToken($client, self::TEST_EMAIL, 'motdepasse123');
        self::assertIsString($token);
        self::assertNotEmpty($token);
    }
}
