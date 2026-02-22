<?php

declare(strict_types=1);

namespace App\Domain\User\Service;

interface JwtBlocklistInterface
{
    public function revoke(string $jti, int $ttlSeconds): void;

    public function isRevoked(string $jti): bool;
}
