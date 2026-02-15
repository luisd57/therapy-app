<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Entity;

use App\Domain\Appointment\Exception\InvalidStatusTransitionException;
use App\Domain\Appointment\ValueObject\AppointmentId;
use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\Appointment\ValueObject\AppointmentStatus;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\ValueObject\UserId;
use DateTimeImmutable;

class Appointment
{
    private AppointmentStatus $status;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly AppointmentId $id,
        private readonly TimeSlot $timeSlot,
        private readonly AppointmentModality $modality,
        private readonly string $fullName,
        private readonly Email $email,
        private readonly Phone $phone,
        private readonly string $city,
        private readonly string $country,
        private readonly ?UserId $patientId,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->status = AppointmentStatus::REQUESTED;
        $this->updatedAt = $createdAt;
    }

    public static function request(
        AppointmentId $id,
        TimeSlot $timeSlot,
        AppointmentModality $modality,
        string $fullName,
        Email $email,
        Phone $phone,
        string $city,
        string $country,
        ?UserId $patientId = null,
    ): self {
        if (trim($fullName) === '') {
            throw new \InvalidArgumentException('Full name is required.');
        }

        if (trim($city) === '') {
            throw new \InvalidArgumentException('City is required.');
        }

        if (trim($country) === '') {
            throw new \InvalidArgumentException('Country is required.');
        }

        return new self(
            id: $id,
            timeSlot: $timeSlot,
            modality: $modality,
            fullName: trim($fullName),
            email: $email,
            phone: $phone,
            city: trim($city),
            country: trim($country),
            patientId: $patientId,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function confirm(): void
    {
        $this->transitionTo(AppointmentStatus::CONFIRMED);
    }

    public function complete(): void
    {
        $this->transitionTo(AppointmentStatus::COMPLETED);
    }

    public function cancel(): void
    {
        $this->transitionTo(AppointmentStatus::CANCELLED);
    }

    private function transitionTo(AppointmentStatus $newStatus): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new InvalidStatusTransitionException($this->status, $newStatus);
        }

        $this->status = $newStatus;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function blocksSlot(): bool
    {
        return $this->status->blocksSlot();
    }

    public function getId(): AppointmentId
    {
        return $this->id;
    }

    public function getTimeSlot(): TimeSlot
    {
        return $this->timeSlot;
    }

    public function getModality(): AppointmentModality
    {
        return $this->modality;
    }

    public function getStatus(): AppointmentStatus
    {
        return $this->status;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getPhone(): Phone
    {
        return $this->phone;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getPatientId(): ?UserId
    {
        return $this->patientId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public static function reconstitute(
        AppointmentId $id,
        TimeSlot $timeSlot,
        AppointmentModality $modality,
        AppointmentStatus $status,
        string $fullName,
        Email $email,
        Phone $phone,
        string $city,
        string $country,
        ?UserId $patientId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        $appointment = new self(
            id: $id,
            timeSlot: $timeSlot,
            modality: $modality,
            fullName: $fullName,
            email: $email,
            phone: $phone,
            city: $city,
            country: $country,
            patientId: $patientId,
            createdAt: $createdAt,
        );

        $appointment->status = $status;
        $appointment->updatedAt = $updatedAt;

        return $appointment;
    }
}
