<?php

declare(strict_types=1);

namespace App\Application\User\DTO\Input;

final readonly class UpdatePatientProfileInputDTO
{
    public function __construct(
        public string $userId,
        public ?string $phone = null,
        public ?string $street = null,
        public ?string $city = null,
        public ?string $country = null,
        public ?string $postalCode = null,
        public ?string $state = null,
    ) {
    }
}