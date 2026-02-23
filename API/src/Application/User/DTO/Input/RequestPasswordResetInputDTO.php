<?php

declare(strict_types=1);

namespace App\Application\User\DTO\Input;

final readonly class RequestPasswordResetInputDTO
{
    public function __construct(
        public string $email,
    ) {
    }
}