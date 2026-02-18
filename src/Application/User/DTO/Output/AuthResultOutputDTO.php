<?php

declare(strict_types=1);

namespace App\Application\User\DTO\Output;

final readonly class AuthResultOutputDTO
{
    public function __construct(
        public string $token,
        public UserOutputDTO $user,
    ) {
    }
}
