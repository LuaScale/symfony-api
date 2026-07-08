<?php

declare(strict_types=1);

namespace App\Tests\Api\Scenario;

use App\DataFixtures\AppFixtures;
use App\Tests\Api\ApiTestCase;

/**
 * User profile management and authentication edge cases.
 */
final class UserProfileTest extends ApiTestCase
{
    private const JSONLD = 'application/ld+json';

    public function testUserUpdatesOwnProfile(): void
    {
        $client = $this->getTestClientAndReloadFixtures();
        $token  = $this->getSellerToken($client);
        $auth   = $this->authHeaders($token);

        // Find seller's IRI
        $client->request('GET', '/api/users', server: ['HTTP_ACCEPT' => self::JSONLD]);
        $users  = $this->assertHydraCollection($this->getJsonResponse($client));
        $seller = $this->findInCollection($users, 'email', AppFixtures::SELLER_EMAIL);
        $userIri = $seller['@id'];

        // Update pseudo and phoneNumber
        $client->request('PATCH', $userIri, server: array_merge([
            'HTTP_ACCEPT'  => self::JSONLD,
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], $auth), content: json_encode([
            'pseudo'      => 'RetroHunterUpdated',
            'phoneNumber' => '+33612345678',
        ]));

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse($client);
        self::assertSame('RetroHunterUpdated', $data['pseudo']);
        self::assertSame('+33612345678', $data['phoneNumber']);
        self::assertArrayNotHasKey('password', $data);
    }

    public function testUserCannotUpdateAnotherUsersProfile(): void
    {
        $client      = $this->getTestClientAndReloadFixtures();
        $sellerToken = $this->getSellerToken($client);

        // Get buyer's IRI
        $client->request('GET', '/api/users', server: ['HTTP_ACCEPT' => self::JSONLD]);
        $users  = $this->assertHydraCollection($this->getJsonResponse($client));
        $buyer  = $this->findInCollection($users, 'email', AppFixtures::BUYER_EMAIL);
        $buyerIri = $buyer['@id'];

        // Seller tries to patch buyer's profile → 403
        $client->request('PATCH', $buyerIri, server: array_merge([
            'HTTP_ACCEPT'  => self::JSONLD,
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], $this->authHeaders($sellerToken)), content: json_encode(['pseudo' => 'Hijacked']));

        self::assertResponseStatusCodeSame(403);
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = $this->getTestClient();

        // Wrong email
        $client->request('POST', '/api/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'nobody@collector.shop', 'password' => 'any'])
        );
        self::assertResponseStatusCodeSame(401);

        // Valid email, wrong password
        $client->request('POST', '/api/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => AppFixtures::SELLER_EMAIL, 'password' => 'wrongpassword'])
        );
        self::assertResponseStatusCodeSame(401);
    }

    public function testRegistrationWithDuplicateEmailReturns422(): void
    {
        $client = $this->getTestClientAndReloadFixtures();

        // Register with an email that already exists in fixtures
        $this->jsonLdRequest($client, 'POST', '/api/users', [
            'email'    => AppFixtures::SELLER_EMAIL,
            'pseudo'   => 'Doublon',
            'password' => 'password123',
        ]);

        $this->assertValidationErrors($client, ['email']);
    }

    public function testRegistrationWithInvalidEmailReturns422(): void
    {
        $client = $this->getTestClient();

        $this->jsonLdRequest($client, 'POST', '/api/users', [
            'email'    => 'not-an-email',
            'pseudo'   => 'TestUser',
            'password' => 'password123',
        ]);

        $this->assertValidationErrors($client, ['email']);
    }

    public function testPasswordChangeHashesNewPassword(): void
    {
        $client = $this->getTestClientAndReloadFixtures();
        $token  = $this->getSellerToken($client);
        $auth   = $this->authHeaders($token);

        $client->request('GET', '/api/users', server: ['HTTP_ACCEPT' => self::JSONLD]);
        $users   = $this->assertHydraCollection($this->getJsonResponse($client));
        $userIri = $this->findInCollection($users, 'email', AppFixtures::SELLER_EMAIL)['@id'];

        // Change password
        $client->request('PATCH', $userIri, server: array_merge([
            'HTTP_ACCEPT'  => self::JSONLD,
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], $auth), content: json_encode(['password' => 'newpassword456']));

        self::assertResponseIsSuccessful();

        // Old password no longer works
        $client->request('POST', '/api/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => AppFixtures::SELLER_EMAIL, 'password' => AppFixtures::SELLER_PASSWORD])
        );
        self::assertResponseStatusCodeSame(401);

        // New password works
        $client->request('POST', '/api/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => AppFixtures::SELLER_EMAIL, 'password' => 'newpassword456'])
        );
        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $this->getJsonResponse($client));
    }
}
