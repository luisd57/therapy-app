<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\EventSubscriber;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

// Not ApiTestCase: the subscriber runs on every response, so the cheapest endpoint
// will do and transaction wrapping and the auth helpers would buy nothing.
final class SecurityHeadersSubscriberTest extends WebTestCase
{
    /** The exact contract SecurityHeadersSubscriber sets. These are the shipped security headers. */
    private const EXPECTED_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '0',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
    ];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testSuccessfulResponseCarriesEverySecurityHeader(): void
    {
        $this->client->request('GET', '/api/health');

        $this->assertResponseIsSuccessful();
        $this->assertSecurityHeadersPresent();
    }

    /**
     * A response the controller never produced, built by the firewall. Not a routing 404:
     * see the `removeCspHeader` entry in `.claude/rules/dev-gotchas.md`.
     */
    public function testUnauthenticatedResponseCarriesEverySecurityHeader(): void
    {
        $this->client->request('GET', '/api/therapist/patients');

        $this->assertResponseStatusCodeSame(401);
        $this->assertSecurityHeadersPresent();
    }

    private function assertSecurityHeadersPresent(): void
    {
        foreach (self::EXPECTED_HEADERS as $name => $value) {
            $this->assertResponseHeaderSame($name, $value, sprintf('Missing or wrong %s header', $name));
        }
    }
}
