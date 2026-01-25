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
    private static bool $fixturesLoaded = false;

    /**
     * Creates a Symfony test client without reloading fixtures.
     * Fixtures are loaded once on first client creation.
     */
    protected function getTestClient(array $options = [], array $server = []): KernelBrowser
    {
        $client = static::createClient($options, $server);

        // Load fixtures only once for all tests
        if (!self::$fixturesLoaded) {
            $this->checkPdoDriver();

            self::getContainer()
                ->get(DatabaseToolCollection::class)
                ->get(null)
                ->loadFixtures([
                    AppFixtures::class,
                ]);

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
        $client = static::createClient($options, $server);

        self::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get(null)
            ->loadFixtures([
                AppFixtures::class,
            ]);

        return $client;
    }

    /**
     * Check if PDO driver is available.
     */
    private function checkPdoDriver(): void
    {
        // Doctrine needs an actual PDO driver (sqlite or pgsql).
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
        // Check @context (JSON-LD requirement)
        self::assertArrayHasKey('@context', $data, 'Hydra collection must have @context');

        // Check @type is hydra:Collection or Collection (both are valid with JSON-LD context)
        $type = $data['@type'] ?? null;
        self::assertContains($type, ['Collection', 'hydra:Collection'], 'Collection @type must be "Collection" or "hydra:Collection"');

        // Check member exists (can be 'member' or 'hydra:member' depending on JSON-LD context)
        $members = $data['member'] ?? $data['hydra:member'] ?? null;
        self::assertIsArray($members, 'Collection must have "member" or "hydra:member" array');

        // Check totalItems exists (can be 'totalItems' or 'hydra:totalItems' depending on JSON-LD context)
        $totalItems = $data['totalItems'] ?? $data['hydra:totalItems'] ?? null;
        self::assertIsInt($totalItems, 'Collection must have "totalItems" or "hydra:totalItems" integer');

        return $members;
    }

    /**
     * Find an item in a collection by a specific field value.
     *
     * @param array<int, array<string, mixed>> $members The collection members
     * @param string $field The field to search by
     * @param mixed $value The value to search for
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
     * Returns an ID that is unlikely to exist in the database.
     *
     * @return int A very high ID value
     */
    protected function getNonExistentId(): int
    {
        // Use a very high ID that is extremely unlikely to exist
        // but still within PostgreSQL integer range (2147483647)
        return 999999999;
    }
}
