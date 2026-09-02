<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\EventSubscriber;

use App\Infrastructure\Http\EventSubscriber\RateLimitSubscriber;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\KeepsRateLimitsAcrossRequests;
use App\Tests\Helper\RateLimitedRoutes;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Symfony\Bundle\SecurityBundle\EventListener\FirewallListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\KernelEvents;

final class RateLimitSubscriberTest extends ApiTestCase
{
    use KeepsRateLimitsAcrossRequests;

    protected function setUp(): void
    {
        parent::setUp();

        // Every test here counts requests, and the swap has to precede the first one.
        $this->useRateLimitStorageThatSurvivesRequests();
    }

    #[DataProviderExternal(RateLimitedRoutes::class, 'ceilings')]
    public function testRouteIsLetThroughUpToItsCeilingThenRejected(string $method, string $url, int $ceiling): void
    {
        $this->sendRequests($method, $url, $ceiling);

        // Everything up to the ceiling got through, so the 429 below cannot come from
        // rejecting every request.
        $this->assertNotSame(429, $this->client->getResponse()->getStatusCode());

        $this->jsonRequest($method, $url, []);

        $this->assertRateLimited($ceiling);
    }

    /**
     * The bucket is keyed per client, so one caller cannot lock everyone else out. Run over
     * both limiters, since a constant key in one arm is invisible from the other.
     */
    #[DataProviderExternal(RateLimitedRoutes::class, 'oneRoutePerLimiter')]
    public function testTheCeilingIsPerClientIp(string $method, string $url, int $ceiling): void
    {
        $this->client->setServerParameter('REMOTE_ADDR', '203.0.113.1');
        $this->sendRequests($method, $url, $ceiling + 1);
        $this->assertResponseStatusCodeSame(429);

        $this->client->setServerParameter('REMOTE_ADDR', '203.0.113.2');
        $this->jsonRequest($method, $url, []);

        $this->assertNotSame(429, $this->client->getResponse()->getStatusCode());
    }

    /**
     * One public ceiling covers the public routes together, so keying per route as well as
     * per client would quietly hand a caller a fresh ceiling for each of them.
     */
    public function testThePublicRoutesShareOneCeiling(): void
    {
        $ceiling = RateLimitedRoutes::ceilingFor('api_forgot_password');
        $half = intdiv($ceiling, 2);

        $this->sendRequests('POST', RateLimitedRoutes::urlFor('api_forgot_password'), $half);
        $this->sendRequests('POST', RateLimitedRoutes::urlFor('api_register'), $ceiling - $half);

        // Split across two routes, the ceiling is reached but not passed.
        $this->assertNotSame(429, $this->client->getResponse()->getStatusCode());

        $this->jsonRequest('POST', RateLimitedRoutes::urlFor('api_forgot_password'), []);

        $this->assertResponseStatusCodeSame(429);
    }

    /**
     * The two ceilings are separate buckets. This is what fails if a route moves to the
     * other arm of resolveLimiter() rather than being dropped from it.
     */
    public function testExhaustingTheLoginLimitLeavesThePublicLimitAlone(): void
    {
        $this->sendRequests('POST', RateLimitedRoutes::urlFor('api_therapist_login'), RateLimitedRoutes::ceilingFor('api_therapist_login') + 1);
        $this->assertResponseStatusCodeSame(429);

        $this->jsonRequest('POST', RateLimitedRoutes::urlFor('api_forgot_password'), []);

        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * Below the firewall a brute-force attempt would authenticate before being counted.
     * Above the router there is no _route to match on yet.
     */
    public function testTheLimiterRunsAfterTheRouterAndBeforeTheFirewall(): void
    {
        $limiter = $this->requestListenerPriority(RateLimitSubscriber::class);

        $this->assertLessThan($this->requestListenerPriority(RouterListener::class), $limiter);
        $this->assertGreaterThan($this->requestListenerPriority(FirewallListener::class), $limiter);
    }

    /** Reads the wired dispatcher, so a priority overridden in services.yaml is caught too. */
    private function requestListenerPriority(string $listenerClass): int
    {
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);

        foreach ($dispatcher->getListeners(KernelEvents::REQUEST) as $listener) {
            $object = is_array($listener) ? $listener[0] : $listener;

            if ($object instanceof $listenerClass) {
                return $dispatcher->getListenerPriority(KernelEvents::REQUEST, $listener);
            }
        }

        self::fail(sprintf('Nothing on kernel.request is a %s', $listenerClass));
    }

    /** Every one of these routes validates an empty body, but only after the limiter consumes. */
    private function sendRequests(string $method, string $url, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->jsonRequest($method, $url, []);
        }
    }

    private function assertRateLimited(int $expectedCeiling): void
    {
        $this->assertResponseStatusCodeSame(429);

        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
        $this->assertSame('RATE_LIMIT_EXCEEDED', $data['error']['code']);
        $this->assertSame('Too many requests. Please try again later.', $data['error']['message']);

        $this->assertResponseHeaderSame('X-RateLimit-Limit', (string) $expectedCeiling);
        // The rejected hit is not counted, so this reports the exhausted window, not a negative.
        $this->assertResponseHeaderSame('X-RateLimit-Remaining', '0');

        // A sliding window frees one token per interval/ceiling, so a hardcoded Retry-After
        // misses the band. The lower bound has two seconds of slack against a slow CI run.
        $slice = intdiv(RateLimitedRoutes::WINDOW_SECONDS, $expectedCeiling);
        $retryAfter = (int) $this->client->getResponse()->headers->get('Retry-After');
        $this->assertGreaterThanOrEqual($slice - 2, $retryAfter);
        $this->assertLessThanOrEqual($slice, $retryAfter);
    }
}
