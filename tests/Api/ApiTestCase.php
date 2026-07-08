<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\DataFixtures\AppFixtures;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use PDO;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use function filter_var;
use function in_array;
use function is_bool;
use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * Base class for API integration tests.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const string CT_JSONLD      = 'application/ld+json';
    protected const string CT_MERGE_PATCH = 'application/merge-patch+json';
    protected const string CT_JSON        = 'application/json';
    protected const string API_ITEMS      = '/api/items';
    protected const string API_SHOPS      = '/api/shops';
    protected const string API_USERS      = '/api/users';
    protected const string API_CATEGORIES = '/api/categories';
    protected const string API_AUTH_LOGIN = '/api/auth/login';

    private static bool $fixturesLoaded = false;

    /**
     * Creates a Symfony test client without reloading fixtures.
     * Fixtures are loaded once on first client creation.
     */
    protected function getTestClient(array $options = [], array $server = []): KernelBrowser
    {
        $client = static::createClient($options, $server);

        if (!self::$fixturesLoaded) {
            $this->checkPdoDriver();

            /** @var DatabaseToolCollection $databaseTool */
            $databaseTool = self::getContainer()->get(DatabaseToolCollection::class);
            $databaseTool->get()->loadFixtures([AppFixtures::class]);

            self::$fixturesLoaded = true;
        }

        return $client;
    }

    /**
     * Creates a Symfony test client and reloads fixtures.
     * Use this only for tests that modify data and need a clean state.
     */
    protected function getTestClientAndReloadFixtures(array $options = [], array $server = []): KernelBrowser
    {
        self::$fixturesLoaded = false;

        $client = static::createClient($options, $server);

        /** @var DatabaseToolCollection $databaseTool */
        $databaseTool = self::getContainer()->get(DatabaseToolCollection::class);
        $databaseTool->get()->loadFixtures([AppFixtures::class]);

        self::$fixturesLoaded = true;

        return $client;
    }

    /**
     * Obtains a JWT token by posting credentials to the login endpoint.
     */
    protected function getJwtToken(KernelBrowser $client, string $email, string $password): string
    {
        $client->request(
            'POST',
            self::API_AUTH_LOGIN,
            server: ['CONTENT_TYPE' => self::CT_JSON],
            content: json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        return $data['token'];
    }

    /**
     * Returns the JWT token for the fixture seller user.
     */
    protected function getSellerToken(KernelBrowser $client): string
    {
        return $this->getJwtToken($client, AppFixtures::SELLER_EMAIL, AppFixtures::SELLER_PASSWORD);
    }

    /**
     * Returns HTTP server headers including a Bearer authorization token.
     *
     * @return array<string, string>
     */
    protected function authHeaders(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
    }

    /**
     * Create a shop via API and return its IRI.
     */
    protected function createShopViaApi(KernelBrowser $client, string $token, string $name, string $description = ''): string
    {
        $this->jsonLdRequest($client, 'POST', self::API_SHOPS, [
            'name' => $name,
            'description' => $description,
            'owner' => $this->getCurrentUserIri($client, $token),
        ], $this->authHeaders($token));

        return $this->assertResourceCreated($client);
    }

    /**
     * Create an item via API and return its IRI.
     */
    protected function createItemViaApi(
        KernelBrowser $client,
        string $token,
        string $name,
        string $shopIri,
        string $categoryIri,
        int $price = 1000,
        string $status = 'DRAFT',
    ): string {
        $this->jsonLdRequest($client, 'POST', self::API_ITEMS, [
            'name' => $name,
            'description' => 'Description for '.$name,
            'price' => $price,
            'status' => $status,
            'shop' => $shopIri,
            'category' => $categoryIri,
        ], $this->authHeaders($token));

        return $this->assertResourceCreated($client);
    }

    /**
     * Return the first category IRI found in the collection.
     */
    protected function getFirstCategoryIri(KernelBrowser $client): string
    {
        $client->request('GET', self::API_CATEGORIES, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        $categories = $this->assertHydraCollection($this->getJsonResponse($client));

        return $categories[0]['@id'];
    }

    /**
     * Return the IRI of the authenticated user by calling /api/users and matching by token email.
     * Relies on the JWT token being valid and the user existing in the users collection.
     */
    private function getCurrentUserIri(KernelBrowser $client, string $token): string
    {
        $client->request('GET', self::API_USERS, server: ['HTTP_ACCEPT' => self::CT_JSONLD]);
        $members = $this->assertHydraCollection($this->getJsonResponse($client));

        // Decode the JWT payload to find the email (base64 URL-encoded middle segment)
        [, $payloadB64] = explode('.', $token);
        $payload = json_decode(base64_decode(str_pad(strtr($payloadB64, '-_', '+/'), strlen($payloadB64) % 4, '=')), true);
        $email = $payload['username'] ?? $payload['email'] ?? '';

        foreach ($members as $user) {
            if (($user['email'] ?? '') === $email) {
                return $user['@id'];
            }
        }

        self::fail('Could not determine current user IRI from token');
    }

    private function checkPdoDriver(): void
    {
        $rawRunningInContainer = $_SERVER['APP_RUNNING_IN_CONTAINER'] ?? $_ENV['APP_RUNNING_IN_CONTAINER'] ?? false;
        if (is_bool($rawRunningInContainer)) {
            $runningInContainer = $rawRunningInContainer;
        } else {
            $runningInContainer = filter_var(
                $rawRunningInContainer,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
            if ($runningInContainer === null) {
                $runningInContainer = false;
            }
        }

        if (!$runningInContainer
            && !in_array('sqlite', PDO::getAvailableDrivers(), true)
            && !in_array('pgsql', PDO::getAvailableDrivers(), true)
        ) {
            self::markTestSkipped('No PDO driver available (need pdo_sqlite or pdo_pgsql) to run API integration tests. Run them in Docker, or enable a PDO driver locally.');
        }
    }

    /**
     * Assert that the response is a valid Hydra collection.
     *
     * @param array<string, mixed> $data The decoded JSON-LD response
     * @return array<int, array<string, mixed>> The collection members
     */
    protected function assertHydraCollection(array $data): array
    {
        self::assertArrayHasKey('@context', $data, 'Hydra collection must have @context');

        $type = $data['@type'] ?? null;
        self::assertContains($type, ['Collection', 'hydra:Collection'], 'Collection @type must be "Collection" or "hydra:Collection"');

        $members = $data['member'] ?? $data['hydra:member'] ?? null;
        self::assertIsArray($members, 'Collection must have "member" or "hydra:member" array');

        $totalItems = $data['totalItems'] ?? $data['hydra:totalItems'] ?? null;
        self::assertIsInt($totalItems, 'Collection must have "totalItems" or "hydra:totalItems" integer');

        return $members;
    }

    /**
     * Find an item in a collection by a specific field value.
     *
     * @param array<int, array<string, mixed>> $members The collection members
     * @return array<string, mixed> The found item
     */
    protected function findInCollection(array $members, string $field, mixed $value): array
    {
        foreach ($members as $item) {
            if (($item[$field] ?? null) === $value) {
                return $item;
            }
        }

        self::fail(sprintf('Item with %s="%s" not found in collection', $field, $value));
    }

    /**
     * Get a non-existent resource ID for testing 404 responses.
     */
    protected function getNonExistentId(): int
    {
        return 999999999;
    }

    /**
     * Make a JSON-LD request with proper headers.
     *
     * @param array<string, string> $extraServer Additional server headers (e.g. auth headers)
     */
    protected function jsonLdRequest(KernelBrowser $client, string $method, string $url, ?array $data = null, array $extraServer = []): void
    {
        $client->request(
            $method,
            $url,
            server: array_merge([
                'HTTP_ACCEPT' => self::CT_JSONLD,
                'CONTENT_TYPE' => self::CT_JSONLD,
            ], $extraServer),
            content: $data ? json_encode($data) : null
        );
    }

    /**
     * Assert that a resource was created successfully and return its IRI.
     */
    protected function assertResourceCreated(KernelBrowser $client): string
    {
        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('@id', $data, 'Created resource must have @id');

        return $data['@id'];
    }

    /**
     * Assert that the response contains validation errors.
     *
     * @param array<string> $expectedFields Expected field names that should have violations
     */
    protected function assertValidationErrors(KernelBrowser $client, array $expectedFields = []): void
    {
        self::assertResponseStatusCodeSame(422);

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('violations', $data, 'Validation error response must have violations');
        self::assertIsArray($data['violations'], 'violations must be an array');
        self::assertNotEmpty($data['violations'], 'violations array must not be empty');

        if (!empty($expectedFields)) {
            $violatedFields = array_map(static fn($v) => $v['propertyPath'], $data['violations']);

            foreach ($expectedFields as $field) {
                self::assertContains(
                    $field,
                    $violatedFields,
                    sprintf('Expected validation error for field "%s"', $field)
                );
            }
        }
    }

    /**
     * Get the decoded JSON response as an array.
     *
     * @return array<string, mixed>
     */
    protected function getJsonResponse(KernelBrowser $client): array
    {
        return json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Assert that a resource was deleted successfully.
     */
    protected function assertResourceDeleted(): void
    {
        self::assertResponseStatusCodeSame(204);
        self::assertEmpty(static::getClient()->getResponse()->getContent());
    }
}
