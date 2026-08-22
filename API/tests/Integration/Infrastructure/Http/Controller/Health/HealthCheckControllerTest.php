<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Health;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

// Not ApiTestCase: this endpoint needs no database transaction and no auth helper.
final class HealthCheckControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testHealthReturnsStatus(): void
    {
        $this->client->request('GET', '/api/health');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame('healthy', $data['status']);
        // Route name and URL are frozen: security.yaml matches ^/api/health for PUBLIC_ACCESS.
        $this->assertSame('api_health', $this->client->getRequest()->attributes->get('_route'));
    }

    public function testHealthResponseContainsTimestamp(): void
    {
        $this->client->request('GET', '/api/health');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('timestamp', $data);
    }
}
