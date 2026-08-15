<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Service;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Timezone;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

interface AppointmentEmailSenderInterface
{
    /**
     * Requester-facing: renders in $requesterTimezone, or the practice zone when it is null.
     */
    public function sendRequestAcknowledgment(
        Email $to,
        string $fullName,
        DateTimeImmutable $appointmentTime,
        AppointmentModality $modality,
        ?Timezone $requesterTimezone,
    ): void;

    /**
     * Therapist-facing: renders in the practice zone, with the requester's time alongside.
     */
    public function sendNewRequestAlertToTherapist(
        Email $therapistEmail,
        string $requesterName,
        DateTimeImmutable $appointmentTime,
        AppointmentModality $modality,
        ?Timezone $requesterTimezone,
    ): void;

    /**
     * Requester-facing: renders in $requesterTimezone, or the practice zone when it is null.
     */
    public function sendConfirmationToPatient(
        Email $to,
        string $fullName,
        DateTimeImmutable $appointmentTime,
        AppointmentModality $modality,
        ?Timezone $requesterTimezone,
    ): void;

    /**
     * Requester-facing: renders in $requesterTimezone, or the practice zone when it is null.
     */
    public function sendCancellationToPatient(
        Email $to,
        string $fullName,
        DateTimeImmutable $appointmentTime,
        AppointmentModality $modality,
        ?Timezone $requesterTimezone,
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
