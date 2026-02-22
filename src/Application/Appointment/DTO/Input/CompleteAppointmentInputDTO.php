<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Input;

final readonly class CompleteAppointmentInputDTO
{
    public function __construct(
        public string $appointmentId,
    ) {
    }
}
