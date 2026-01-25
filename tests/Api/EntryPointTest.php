<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class EntryPointTest extends ApiTestCase
{
    private const ACCEPT_JSONLD = 'application/ld+json';

    public function testGetApiEntryPoint(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', '/api', server: ['HTTP_ACCEPT' => self::ACCEPT_JSONLD]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('/api', $data['@id'] ?? null);
        self::assertArrayHasKey('@context', $data);

        // Ensure main resources are discoverable from the entrypoint.
        self::assertArrayHasKey('item', $data);
        self::assertArrayHasKey('category', $data);
        self::assertArrayHasKey('shop', $data);
        self::assertArrayHasKey('user', $data);
    }
}
