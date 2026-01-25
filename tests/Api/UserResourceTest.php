<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class UserResourceTest extends ApiTestCase
{
    private const ACCEPT_JSONLD = 'application/ld+json';
    private const FIXTURE_USER_EMAIL = 'vendeur@collector.shop';

    public function testGetUsersCollectionContainsFixtureUserAndHasExpectedTypes(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', '/api/users', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        $members = $data['member'] ?? $data['hydra:member'] ?? null;

        self::assertIsArray($members);
        self::assertNotEmpty($members);

        // Find the fixture user by email
        $fixtureUser = null;
        foreach ($members as $user) {
            if (($user['email'] ?? '') === self::FIXTURE_USER_EMAIL) {
                $fixtureUser = $user;
                break;
            }
        }

        self::assertIsArray($fixtureUser, 'Fixture user "' . self::FIXTURE_USER_EMAIL . '" not found in collection');
        self::assertIsString($fixtureUser['email'] ?? null);
        self::assertIsString($fixtureUser['pseudo'] ?? null);

        // roles is expected to be an array (list of strings)
        if (array_key_exists('roles', $fixtureUser)) {
            self::assertIsArray($fixtureUser['roles']);
        }

        // isVerified might be serialized as boolean
        if (array_key_exists('isVerified', $fixtureUser)) {
            self::assertIsBool($fixtureUser['isVerified']);
        }

        $id = $fixtureUser['@id'] ?? null;
        self::assertIsString($id);
        self::assertStringStartsWith('/api/users/', $id);

        $client->request('GET', $id, server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);
        self::assertResponseIsSuccessful();
    }

    public function testUnknownUserReturns404(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', '/api/users/999999999', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);

        self::assertResponseStatusCodeSame(404);
    }
}
