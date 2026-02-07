<?php

declare(strict_types=1);

namespace App\Application\User\DTO\Input;

final readonly class ResetPasswordInputDTO
{
    public function __construct(
        public string $token,
        public string $newPassword,
    ) {
    }
}