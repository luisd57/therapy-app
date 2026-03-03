<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Service;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\User\ValueObject\Email;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

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

    public function sendConfirmationToPatient(
        Email $to,
        string $fullName,
        DateTimeImmutable $appointmentTime,
        AppointmentModality $modality,
    ): void;

    public function sendCancellationToPatient(
        Email $to,
        string $fullName,
        DateTimeImmutable $appointmentTime,
        AppointmentModality $modality,
    ): void;

    /**
     * @param ArrayCollection<int, Appointment> $appointments
     */
    public function sendDailyAgendaToTherapist(
        Email $therapistEmail,
        string $therapistName,
        DateTimeImmutable $date,
        ArrayCollection $appointments,
    ): void;
}
