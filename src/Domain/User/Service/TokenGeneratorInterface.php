<?php

declare(strict_types=1);

namespace App\Domain\User\Service;

interface TokenGeneratorInterface
{
    /**
     * Generates a cryptographically secure random token.
     */
    public function generate(int $length = 64): string;
}
