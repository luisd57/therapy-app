<?php

declare(strict_types=1);

namespace App\Application\User\DTO\Output;

final readonly class AuthResultDTO
{
    public function __construct(
        public string $token,
        public UserDTO $user,
    ) {
    }
}
