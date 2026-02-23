<?php

declare(strict_types=1);

namespace App\Application\Appointment\Service;

use App\Application\Appointment\DTO\Output\AppointmentOutputDTO;

interface AppointmentRequestServiceInterface
{
    public function requestAppointment(
        string $slotStartTime,
        string $modality,
        string $fullName,
        string $phone,
        string $email,
        string $city,
        string $country,
        ?string $lockToken = null,
        ?string $patientId = null,
    ): AppointmentOutputDTO;
}
