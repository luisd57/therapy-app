<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Input;

final readonly class BookAppointmentInputDTO
{
    public function __construct(
        public string $slotStartTime,
        public string $modality,
        public string $fullName,
        public string $phone,
        public string $email,
        public string $city,
        public string $country,
        public ?string $patientId = null,
    ) {
    }
}
