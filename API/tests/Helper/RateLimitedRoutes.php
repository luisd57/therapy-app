<?php

declare(strict_types=1);

namespace App\Tests\Helper;

/**
 * The routes RateLimitSubscriber limits and the ceiling each is held to, owned here so the
 * two rate limit test files cannot drift apart. Pinned from config/packages/rate_limiter.yaml.
 */
final class RateLimitedRoutes
{
    private const LOGIN_CEILING = 5;
    private const PUBLIC_CEILING = 10;

    /** interval: '1 minute' on both limiters. */
    public const WINDOW_SECONDS = 60;

    /** One entry per route RateLimitSubscriber::resolveLimiter() matches. */
    private const ROUTES = [
        'therapist login' => ['route' => 'api_therapist_login', 'method' => 'POST', 'url' => '/api/auth/therapist/login', 'ceiling' => self::LOGIN_CEILING],
        'patient login' => ['route' => 'api_patient_login', 'method' => 'POST', 'url' => '/api/auth/patient/login', 'ceiling' => self::LOGIN_CEILING],
        'forgot password' => ['route' => 'api_forgot_password', 'method' => 'POST', 'url' => '/api/auth/password/forgot', 'ceiling' => self::PUBLIC_CEILING],
        'reset password' => ['route' => 'api_reset_password', 'method' => 'POST', 'url' => '/api/auth/password/reset', 'ceiling' => self::PUBLIC_CEILING],
        'register' => ['route' => 'api_register', 'method' => 'POST', 'url' => '/api/auth/register', 'ceiling' => self::PUBLIC_CEILING],
        'validate invitation' => ['route' => 'api_validate_invitation', 'method' => 'GET', 'url' => '/api/auth/invitation/validate/no-such-token', 'ceiling' => self::PUBLIC_CEILING],
        'lock slot' => ['route' => 'api_lock_slot', 'method' => 'POST', 'url' => '/api/appointments/lock-slot', 'ceiling' => self::PUBLIC_CEILING],
        'request appointment' => ['route' => 'api_request_appointment', 'method' => 'POST', 'url' => '/api/appointments/request', 'ceiling' => self::PUBLIC_CEILING],
    ];

    /** @return iterable<string, array{string, string, int}> */
    public static function ceilings(): iterable
    {
        foreach (self::ROUTES as $case => $limitedRoute) {
            yield $case => [$limitedRoute['method'], $limitedRoute['url'], $limitedRoute['ceiling']];
        }
    }

    /**
     * One route per limiter, for tests that only need to tell the two buckets apart.
     *
     * @return iterable<string, array{string, string, int}>
     */
    public static function oneRoutePerLimiter(): iterable
    {
        $seen = [];

        foreach (self::ROUTES as $case => $limitedRoute) {
            if (!isset($seen[$limitedRoute['ceiling']])) {
                $seen[$limitedRoute['ceiling']] = true;

                yield $case => [$limitedRoute['method'], $limitedRoute['url'], $limitedRoute['ceiling']];
            }
        }
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_column(self::ROUTES, 'route');
    }

    /** The larger ceiling, so one bucket's worth of requests can exhaust either limiter. */
    public static function highestCeiling(): int
    {
        return max(array_column(self::ROUTES, 'ceiling'));
    }

    public static function urlFor(string $routeName): string
    {
        return self::rowFor($routeName)['url'];
    }

    public static function ceilingFor(string $routeName): int
    {
        return self::rowFor($routeName)['ceiling'];
    }

    /** @return array{route: string, method: string, url: string, ceiling: int} */
    private static function rowFor(string $routeName): array
    {
        foreach (self::ROUTES as $limitedRoute) {
            if ($limitedRoute['route'] === $routeName) {
                return $limitedRoute;
            }
        }

        throw new \InvalidArgumentException(sprintf('%s is not a rate limited route', $routeName));
    }
}
