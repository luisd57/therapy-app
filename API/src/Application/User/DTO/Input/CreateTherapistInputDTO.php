<?php

declare(strict_types=1);

namespace App\Application\User\DTO\Input;

final readonly class CreateTherapistInputDTO
{
    public function __construct(
        public string $email,
        public string $fullName,
        public string $password,
    ) {
    }
}