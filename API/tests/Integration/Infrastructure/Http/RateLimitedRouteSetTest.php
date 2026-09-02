<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http;

use App\Infrastructure\Http\EventSubscriber\RateLimitSubscriber;
use App\Tests\Helper\RateLimitedRoutes;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Exactly the routes with a pinned ceiling are rate limited, and nothing else is.
 * RateLimitSubscriberTest catches a route being dropped. Only this catches one being added.
 */
final class RateLimitedRouteSetTest extends KernelTestCase
{
    // Not IntegrationTestCase: this drives the subscriber over every route in the router and
    // never touches the database, so the transaction wrapping would buy nothing.

    private ?RateLimitSubscriber $subscriber = null;

    public function testExactlyTheRoutesWithAPinnedCeilingAreLimited(): void
    {
        $limited = [];

        foreach ($this->routeNames() as $index => $routeName) {
            // A fresh client IP per route, because the limiter keys by it. One shared
            // address would pool every route's hits and skew every result after the first.
            $clientIp = sprintf('10.0.%d.%d', intdiv($index, 256), $index % 256);

            if ($this->isRateLimited($routeName, $clientIp)) {
                $limited[] = $routeName;
            }
        }

        $expected = RateLimitedRoutes::names();

        sort($limited);
        sort($expected);

        // Cannot pass vacuously: an empty router, or a route renamed out from under the
        // subscriber, leaves the two unequal.
        self::assertSame($expected, $limited);
    }

    /**
     * A request the router never matched carries no _route. The subscriber defaults that to
     * the empty string, and a default naming a real route would bucket every one of them.
     */
    public function testARequestWithNoRouteIsNotLimited(): void
    {
        $subscriber = $this->subscriber();

        for ($attempt = 0; $attempt <= RateLimitedRoutes::highestCeiling(); $attempt++) {
            $requestEvent = $this->requestEventFor(null, '10.255.255.1');
            $subscriber->onKernelRequest($requestEvent);

            self::assertNull($requestEvent->getResponse());
        }
    }

    /**
     * Drives the public seam rather than reading the match expression, so deleting the
     * setResponse(), or reading the wrong request attribute, fails here too.
     */
    private function isRateLimited(string $routeName, string $clientIp): bool
    {
        $subscriber = $this->subscriber();

        for ($attempt = 0; $attempt <= RateLimitedRoutes::highestCeiling(); $attempt++) {
            $requestEvent = $this->requestEventFor($routeName, $clientIp);
            $subscriber->onKernelRequest($requestEvent);

            if ($requestEvent->getResponse()?->getStatusCode() === 429) {
                return true;
            }
        }

        return false;
    }

    /** A null route name leaves _route unset, as an unmatched request has it. */
    private function requestEventFor(?string $routeName, string $clientIp): RequestEvent
    {
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => $clientIp]);

        if ($routeName !== null) {
            $request->attributes->set('_route', $routeName);
        }

        return new RequestEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function subscriber(): RateLimitSubscriber
    {
        return $this->subscriber ??= self::getContainer()->get(RateLimitSubscriber::class);
    }

    /** @return list<string> */
    private function routeNames(): array
    {
        $router = self::getContainer()->get(RouterInterface::class);

        return array_keys($router->getRouteCollection()->all());
    }
}
