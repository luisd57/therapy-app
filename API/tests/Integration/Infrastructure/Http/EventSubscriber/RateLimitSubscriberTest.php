<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\EventSubscriber;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\KeepsRateLimitsAcrossRequests;
use PHPUnit\Framework\Attributes\DataProvider;

final class RateLimitSubscriberTest extends ApiTestCase
{
    use KeepsRateLimitsAcrossRequests;

    /**
     * Pinned from config/packages/rate_limiter.yaml and documented in
     * .claude/rules/api-security.md. Loosening either ceiling has to fail here.
     */
    private const LOGIN_LIMIT = 5;
    private const PUBLIC_LIMIT = 10;

    /** interval: '1 minute' on both limiters, so Retry-After can never exceed it. */
    private const WINDOW_SECONDS = 60;

    private const LOGIN_URL = '/api/auth/therapist/login';
    private const PUBLIC_URL = '/api/auth/password/forgot';

    protected function setUp(): void
    {
        parent::setUp();

        // Every test here counts requests, and the swap has to precede the first one.
        $this->useRateLimitStorageThatSurvivesRequests();
    }

    /**
     * One case per route RateLimitSubscriber::resolveLimiter() matches. Dropping any of
     * them has to fail, not just the two this ticket started from.
     *
     * @return iterable<string, array{string, string, int}>
     */
    public static function limitedRoutes(): iterable
    {
        yield 'therapist login' => ['POST', '/api/auth/therapist/login', self::LOGIN_LIMIT];
        yield 'patient login' => ['POST', '/api/auth/patient/login', self::LOGIN_LIMIT];

        yield 'forgot password' => ['POST', '/api/auth/password/forgot', self::PUBLIC_LIMIT];
        yield 'reset password' => ['POST', '/api/auth/password/reset', self::PUBLIC_LIMIT];
        yield 'register' => ['POST', '/api/auth/register', self::PUBLIC_LIMIT];
        yield 'validate invitation' => ['GET', '/api/auth/invitation/validate/no-such-token', self::PUBLIC_LIMIT];
        yield 'lock slot' => ['POST', '/api/appointments/lock-slot', self::PUBLIC_LIMIT];
        yield 'request appointment' => ['POST', '/api/appointments/request', self::PUBLIC_LIMIT];
    }

    #[DataProvider('limitedRoutes')]
    public function testRouteIsLetThroughUpToItsCeilingThenRejected(string $method, string $url, int $limit): void
    {
        $this->sendRequests($method, $url, $limit);

        // Everything up to the ceiling got through, so the 429 below cannot come from
        // rejecting every request.
        $this->assertNotSame(429, $this->client->getResponse()->getStatusCode());

        $this->jsonRequest($method, $url, []);

        $this->assertRateLimited($limit);
    }

    /**
     * The two ceilings are separate buckets. This is what fails if a route moves to the
     * other arm of resolveLimiter() rather than being dropped from it.
     */
    public function testExhaustingTheLoginLimitLeavesThePublicLimitAlone(): void
    {
        $this->sendRequests('POST', self::LOGIN_URL, self::LOGIN_LIMIT + 1);
        $this->assertResponseStatusCodeSame(429);

        $this->jsonRequest('POST', self::PUBLIC_URL, []);

        $this->assertResponseStatusCodeSame(422);
    }

    /** Every one of these routes validates an empty body, but only after the limiter consumes. */
    private function sendRequests(string $method, string $url, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->jsonRequest($method, $url, []);
        }
    }

    private function assertRateLimited(int $expectedLimit): void
    {
        $this->assertResponseStatusCodeSame(429);

        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
        $this->assertSame('RATE_LIMIT_EXCEEDED', $data['error']['code']);
        $this->assertSame('Too many requests. Please try again later.', $data['error']['message']);

        $this->assertResponseHeaderSame('X-RateLimit-Limit', (string) $expectedLimit);
        // The rejected hit is not counted, so this reports the exhausted window, not a negative.
        $this->assertResponseHeaderSame('X-RateLimit-Remaining', '0');

        $retryAfter = (int) $this->client->getResponse()->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(self::WINDOW_SECONDS, $retryAfter);
    }
}
