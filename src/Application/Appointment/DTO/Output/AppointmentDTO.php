<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Output;

use App\Domain\Appointment\Entity\Appointment;
use DateTimeInterface;

final readonly class AppointmentDTO
{
    public function __construct(
        public string $id,
        public string $startTime,
        public string $endTime,
        public string $modality,
        public string $status,
        public string $fullName,
        public string $email,
        public string $phone,
        public string $city,
        public string $country,
        public string $createdAt,
    ) {
    }

    public static function fromEntity(Appointment $appointment): self
    {
        return new self(
            id: $appointment->getId()->getValue(),
            startTime: $appointment->getTimeSlot()->getStartTime()->format(DateTimeInterface::ATOM),
            endTime: $appointment->getTimeSlot()->getEndTime()->format(DateTimeInterface::ATOM),
            modality: $appointment->getModality()->value,
            status: $appointment->getStatus()->value,
            fullName: $appointment->getFullName(),
            email: $appointment->getEmail()->getValue(),
            phone: $appointment->getPhone()->getValue(),
            city: $appointment->getCity(),
            country: $appointment->getCountry(),
            createdAt: $appointment->getCreatedAt()->format(DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'modality' => $this->modality,
            'status' => $this->status,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'country' => $this->country,
            'created_at' => $this->createdAt,
        ];
    }
}
