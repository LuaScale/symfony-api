<?php

declare(strict_types=1);

namespace App\Tests\Api\Scenario;

use App\Tests\Api\ApiTestCase;

/**
 * Category write operations are admin-only — verifies the access control boundary.
 */
final class CategoryAccessControlTest extends ApiTestCase
{
    public function testAnonymousCanReadCategories(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', self::API_CATEGORIES, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        self::assertResponseIsSuccessful();
        $this->assertHydraCollection($this->getJsonResponse($client));

        // Individual category
        $categories  = $this->assertHydraCollection($this->getJsonResponse($client));
        $categoryIri = $categories[0]['@id'];
        $client->request('GET', $categoryIri, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        self::assertResponseIsSuccessful();
    }

    public function testNonAdminCannotCreateCategory(): void
    {
        $client = $this->getTestClient();
        $auth   = $this->authHeaders($this->getSellerToken($client));

        $this->jsonLdRequest($client, 'POST', self::API_CATEGORIES, [
            'name' => 'Intrus',
            'slug' => 'intrus',
        ], $auth);

        self::assertResponseStatusCodeSame(403);
    }

    public function testNonAdminCannotUpdateCategory(): void
    {
        $client = $this->getTestClient();
        $auth   = $this->authHeaders($this->getSellerToken($client));

        $client->request('GET', self::API_CATEGORIES, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        $categories  = $this->assertHydraCollection($this->getJsonResponse($client));
        $categoryIri = $categories[0]['@id'];

        $client->request('PATCH', $categoryIri, server: array_merge([
            'HTTP_ACCEPT'  => self::CT_JSONLD,
            'CONTENT_TYPE' => self::CT_MERGE_PATCH,
        ], $auth), content: json_encode(['name' => 'Renommé']));

        self::assertResponseStatusCodeSame(403);
    }

    public function testNonAdminCannotDeleteCategory(): void
    {
        $client = $this->getTestClient();
        $auth   = $this->authHeaders($this->getSellerToken($client));

        $client->request('GET', self::API_CATEGORIES, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        $categories  = $this->assertHydraCollection($this->getJsonResponse($client));
        $categoryIri = $categories[0]['@id'];

        $client->request('DELETE', $categoryIri, server: $auth);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnauthenticatedCannotWriteCategory(): void
    {
        $client = $this->getTestClient();

        $this->jsonLdRequest($client, 'POST', self::API_CATEGORIES, ['name' => 'Anonyme', 'slug' => 'anonyme']);
        self::assertResponseStatusCodeSame(401);
    }
}
