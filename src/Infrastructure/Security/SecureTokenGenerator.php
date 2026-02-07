<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\Service\TokenGeneratorInterface;

final class SecureTokenGenerator implements TokenGeneratorInterface
{
    public function generate(int $length = 64): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}
