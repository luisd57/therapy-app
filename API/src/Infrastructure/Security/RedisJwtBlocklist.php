<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\Service\JwtBlocklistInterface;
use Psr\Cache\CacheItemPoolInterface;

final class RedisJwtBlocklist implements JwtBlocklistInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {}

    public function revoke(string $jti, int $ttlSeconds): void
    {
        $item = $this->cache->getItem($this->cacheKey($jti));
        $item->set(true);
        $item->expiresAfter($ttlSeconds);
        $this->cache->save($item);
    }

    public function isRevoked(string $jti): bool
    {
        return $this->cache->getItem($this->cacheKey($jti))->isHit();
    }

    public function revokeIssuedAtOrBefore(string $userIdentifier, int $cutoff, int $ttlSeconds): void
    {
        $item = $this->cache->getItem($this->cutoffKey($userIdentifier));
        $item->set($cutoff);
        $item->expiresAfter($ttlSeconds);
        $this->cache->save($item);
    }

    public function isIssuedAtOrBefore(string $userIdentifier, int $issuedAt): bool
    {
        $item = $this->cache->getItem($this->cutoffKey($userIdentifier));

        return $item->isHit() && $issuedAt <= (int) $item->get();
    }

    private function cacheKey(string $jti): string
    {
        return 'therapy_jwt_revoked_' . $jti;
    }

    // Hashed so an email never lands in a cache key.
    private function cutoffKey(string $userIdentifier): string
    {
        return 'therapy_jwt_min_iat_' . hash('sha256', $userIdentifier);
    }
}
