<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Health;

use App\Tests\Helper\Json;
use App\Tests\Helper\WireShapes;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

// Not ApiTestCase: this endpoint needs no database transaction and no auth helper.
final class ApiRootControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testApiRootReturnsApiInfo(): void
    {
        $this->client->request('GET', '/api/');

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->assertTrue(Json::at($data, 'success'));
        $this->assertEqualsCanonicalizing(WireShapes::SUCCESS_ENVELOPE, array_keys(Json::arrayAt($data)), 'envelope keys');
        $this->assertEqualsCanonicalizing(['name', 'version', 'endpoints'], array_keys(Json::arrayAt($data, 'data')), 'data keys');
        $this->assertEqualsCanonicalizing(
            ['health', 'auth', 'therapist', 'patient'],
            array_keys(Json::arrayAt($data, 'data', 'endpoints')),
            'data.endpoints keys',
        );
        // Route name and URL are frozen: security.yaml matches ^/api/$ exactly, trailing slash included.
        $this->assertSame('api_index', $this->client->getRequest()->attributes->get('_route'));
    }
}
