<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Entity;

use App\Domain\Appointment\Exception\InvalidStatusTransitionException;
use App\Domain\Appointment\Id\AppointmentId;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Enum\AppointmentStatus;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\Id\UserId;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'appointments')]
#[ORM\Index(columns: ['status'], name: 'idx_appointment_status')]
#[ORM\Index(columns: ['start_time', 'end_time'], name: 'idx_appointment_time_range')]
#[ORM\Index(columns: ['status', 'start_time', 'end_time'], name: 'idx_appointment_blocking')]
class Appointment
{
    #[ORM\Column(type: Types::STRING, length: 20, enumType: AppointmentStatus::class)]
    private AppointmentStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $paymentVerified = false;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'appointment_id')]
        private readonly AppointmentId $id,
        #[ORM\Embedded(class: TimeSlot::class, columnPrefix: false)]
        private readonly TimeSlot $timeSlot,
        #[ORM\Column(type: Types::STRING, length: 20, enumType: AppointmentModality::class)]
        private readonly AppointmentModality $modality,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private readonly string $fullName,
        #[ORM\Column(type: 'email', length: 255)]
        private readonly Email $email,
        #[ORM\Column(type: 'phone', length: 50)]
        private readonly Phone $phone,
        #[ORM\Column(type: Types::STRING, length: 100)]
        private readonly string $city,
        #[ORM\Column(type: Types::STRING, length: 100)]
        private readonly string $country,
        #[ORM\Column(type: 'user_id', nullable: true)]
        private readonly ?UserId $patientId,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
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

    public static function book(
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

        $appointment = new self(
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

        $appointment->status = AppointmentStatus::CONFIRMED;

        return $appointment;
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

    public function markPaymentVerified(): void
    {
        $this->paymentVerified = true;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markPaymentUnverified(): void
    {
        $this->paymentVerified = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isPaymentVerified(): bool
    {
        return $this->paymentVerified;
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
        bool $paymentVerified = false,
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
        $appointment->paymentVerified = $paymentVerified;

        return $appointment;
    }
}
