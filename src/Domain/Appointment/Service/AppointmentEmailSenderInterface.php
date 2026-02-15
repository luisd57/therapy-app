<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Service;

use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\User\ValueObject\Email;
use DateTimeImmutable;

interface AppointmentEmailSenderInterface
{
    public function sendRequestAcknowledgment(
        Email $to,
        string $fullName,
        DateTimeImmutable $appointmentTime,
        AppointmentModality $modality,
    ): void;

    public function sendNewRequestAlertToTherapist(
        Email $therapistEmail,
        string $requesterName,
        DateTimeImmutable $appointmentTime,
        AppointmentModality $modality,
    ): void;
}
