<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\Service\TokenGeneratorInterface;

final class SecureTokenGenerator implements TokenGeneratorInterface
{
    /**
     * @param int $length Hex output length (each 2 hex chars = 1 byte of entropy). Must be >= 32 (16 bytes).
     */
    public function generate(int $length = 64): string
    {
        if ($length < 32) {
            throw new \InvalidArgumentException('Token length must be at least 32 characters (16 bytes of entropy).');
        }

        return bin2hex(random_bytes(intdiv($length, 2)));
    }
}
