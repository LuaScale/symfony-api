<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class UserResourceTest extends ApiTestCase
{
    public function testGetUsersCollectionContainsFixtureUserAndHasExpectedTypes(): void
    {
        $client = $this->createClientAndLoadFixtures();

        $client->request('GET', '/api/users', ['headers' => ['Accept' => 'application/ld+json']]);
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        $members = $data['member'] ?? $data['hydra:member'] ?? null;

        self::assertIsArray($members);
        self::assertNotEmpty($members);

        $emails = array_values(array_filter(array_map(static fn ($u) => $u['email'] ?? null, $members)));
        self::assertContains('vendeur@collector.shop', $emails);

        $user = $members[0];
        self::assertIsArray($user);

        self::assertIsString($user['email'] ?? null);
        self::assertIsString($user['pseudo'] ?? null);

        // roles is expected to be an array (list of strings)
        if (array_key_exists('roles', $user)) {
            self::assertIsArray($user['roles']);
        }

        // isVerified might be serialized as boolean
        if (array_key_exists('isVerified', $user)) {
            self::assertIsBool($user['isVerified']);
        }

        $id = $user['@id'] ?? null;
        self::assertIsString($id);
        self::assertStringStartsWith('/api/users/', $id);

        $client->request('GET', $id, ['headers' => ['Accept' => 'application/ld+json']]);
        self::assertResponseIsSuccessful();
    }

    public function testUnknownUserReturns404(): void
    {
        $client = $this->createClientAndLoadFixtures();
        $client->request('GET', '/api/users/999999999', ['headers' => ['Accept' => 'application/ld+json']]);

        self::assertResponseStatusCodeSame(404);
    }
}

