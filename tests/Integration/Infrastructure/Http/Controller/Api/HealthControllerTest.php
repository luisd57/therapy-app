<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
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
    }

    public function testHealthResponseContainsTimestamp(): void
    {
        $this->client->request('GET', '/api/health');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function testIndexReturnsApiInfo(): void
    {
        $this->client->request('GET', '/api/');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('name', $data['data']);
        $this->assertArrayHasKey('version', $data['data']);
    }

    public function testIndexListsEndpoints(): void
    {
        $this->client->request('GET', '/api/');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('endpoints', $data['data']);
    }
}
