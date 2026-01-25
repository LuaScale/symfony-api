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
}
