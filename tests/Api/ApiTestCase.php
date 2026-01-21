<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base class for API integration tests.
 */
abstract class ApiTestCase extends WebTestCase
{
    /**
     * Creates a Symfony test client and reloads fixtures.
     *
     * We do this here (not in setUp) to avoid booting the kernel before
     * WebTestCase::createClient(), which Symfony forbids.
     */
    protected function createClientAndLoadFixtures(array $options = [], array $server = []): KernelBrowser
    {
        $client = static::createClient($options, $server);

        // Doctrine needs an actual PDO driver (sqlite or pgsql).
        $rawRunningInContainer = $_SERVER['APP_RUNNING_IN_CONTAINER'] ?? $_ENV['APP_RUNNING_IN_CONTAINER'] ?? false;
        if (\is_bool($rawRunningInContainer)) {
            $runningInContainer = $rawRunningInContainer;
        } else {
            $runningInContainer = \filter_var(
                $rawRunningInContainer,
                \FILTER_VALIDATE_BOOLEAN,
                \FILTER_NULL_ON_FAILURE
            );
            if ($runningInContainer === null) {
                $runningInContainer = false;
            }
        }

        if (!$runningInContainer
            && !\in_array('sqlite', \PDO::getAvailableDrivers(), true)
            && !\in_array('pgsql', \PDO::getAvailableDrivers(), true)
        ) {
            self::markTestSkipped('No PDO driver available (need pdo_sqlite or pdo_pgsql) to run API integration tests. Run them in Docker, or enable a PDO driver locally.');
        }

        self::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get(null)
            ->loadFixtures([
                \App\DataFixtures\AppFixtures::class,
            ]);

        return $client;
    }
}
