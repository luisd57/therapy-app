<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Input;

final readonly class PatientRequestAppointmentInputDTO
{
    public function __construct(
        public string $patientId,
        public string $slotStartTime,
        public string $modality,
        public ?string $lockToken = null,
    ) {
    }
}
