<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Swaps both rate limiters onto storage that outlives a request, for the tests that
 * need the sliding window to still hold the earlier requests' hits.
 */
trait KeepsRateLimitsAcrossRequests
{
    /**
     * Call before the test's first request. Why both are needed: the `ArrayAdapter` entry
     * in `.claude/rules/dev-gotchas.md`.
     */
    protected function useRateLimitStorageThatSurvivesRequests(): void
    {
        $pool = new FilesystemAdapter('rate-limiter-test', 0, sys_get_temp_dir());
        $pool->clear();

        foreach (['api_login', 'api_public'] as $limiter) {
            self::getContainer()->set('limiter.storage.' . $limiter, new CacheStorage($pool));
        }
    }
}
