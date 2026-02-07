<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\Service\PasswordHasherInterface;

final class BcryptPasswordHasher implements PasswordHasherInterface
{
    public function __construct(
        private readonly int $cost = 12,
    ) {
    }

    public function hash(string $plainPassword): string
    {
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => $this->cost]);

        if ($hash === false) {
            throw new \RuntimeException('Failed to hash password.');
        }

        return $hash;
    }

    public function verify(string $plainPassword, string $hashedPassword): bool
    {
        return password_verify($plainPassword, $hashedPassword);
    }
}
