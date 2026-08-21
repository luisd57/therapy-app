<?php

declare(strict_types=1);

namespace App\Domain\User\Service;

interface JwtBlocklistInterface
{
    public function revoke(string $jti, int $ttlSeconds): void;

    public function isRevoked(string $jti): bool;

    // Ends every session for a user at once, which the per-jti pair cannot do.
    public function revokeIssuedAtOrBefore(string $userIdentifier, int $cutoff, int $ttlSeconds): void;

    public function isRevokedByCutoff(string $userIdentifier, int $issuedAt): bool;
}
