<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Domain\User\Service\JwtBlocklistInterface;
use App\Infrastructure\Security\RedisJwtBlocklist;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Swaps the JWT blocklist onto storage that outlives a request, for the tests that
 * revoke a token in one request and expect the next one to be rejected.
 */
trait KeepsBlocklistAcrossRequests
{
    /**
     * The test container's cache.app is an ArrayAdapter that Symfony resets between
     * requests, so a revocation written by one request is gone by the next.
     */
    protected function useBlocklistThatSurvivesRequests(): void
    {
        $pool = new FilesystemAdapter('jwt-blocklist-test', 0, sys_get_temp_dir());
        $pool->clear();

        self::getContainer()->set(JwtBlocklistInterface::class, new RedisJwtBlocklist($pool));
    }
}
