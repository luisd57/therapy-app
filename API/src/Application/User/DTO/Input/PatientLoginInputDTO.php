<?php

declare(strict_types=1);

namespace App\Application\User\DTO\Input;

final readonly class PatientLoginInputDTO
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }
}
