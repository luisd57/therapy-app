<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\Service\PasswordHasherInterface;

/**
 * Single source of truth for password hashing.
 * The password_hashers config in security.yaml is deliberately unused; everything goes through here.
 */
final class BcryptPasswordHasher implements PasswordHasherInterface
{
    public function __construct(
        private readonly int $cost = 12,
    ) {
    }

    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function verify(string $plainPassword, string $hashedPassword): bool
    {
        return password_verify($plainPassword, $hashedPassword);
    }
}
