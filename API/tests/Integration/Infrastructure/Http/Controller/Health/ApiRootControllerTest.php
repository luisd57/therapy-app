<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Health;

use App\Tests\Helper\Json;
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
        $this->assertArrayHasKey('name', Json::arrayAt($data, 'data'));
        $this->assertArrayHasKey('version', Json::arrayAt($data, 'data'));
        // Route name and URL are frozen: security.yaml matches ^/api/$ exactly, trailing slash included.
        $this->assertSame('api_index', $this->client->getRequest()->attributes->get('_route'));
    }

    public function testApiRootListsEndpoints(): void
    {
        $this->client->request('GET', '/api/');

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('endpoints', Json::arrayAt($data, 'data'));
    }
}
