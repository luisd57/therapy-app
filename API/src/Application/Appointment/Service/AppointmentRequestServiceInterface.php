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
        /** Requester's IANA zone, recorded so the therapist knows what time
         *  the session is for them. Null when the client did not report one. */
        ?string $requesterTimezone = null,
    ): AppointmentOutputDTO;
}
