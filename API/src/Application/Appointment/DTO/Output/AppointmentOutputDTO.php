<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Output;

use App\Application\Shared\InstantFormatter;
use App\Domain\Appointment\Entity\Appointment;

final readonly class AppointmentOutputDTO
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
        public ?string $patientId,
        public bool $paymentVerified,
        public string $createdAt,
        public string $updatedAt,
        /** Null for appointments booked before the zone was captured. */
        public ?string $requesterTimezone = null,
    ) {
    }

    public static function fromEntity(Appointment $appointment): self
    {
        return new self(
            id: $appointment->getId()->getValue(),
            startTime: InstantFormatter::toAtomUtc($appointment->getTimeSlot()->getStartTime()),
            endTime: InstantFormatter::toAtomUtc($appointment->getTimeSlot()->getEndTime()),
            modality: $appointment->getModality()->value,
            status: $appointment->getStatus()->value,
            fullName: $appointment->getFullName(),
            email: $appointment->getEmail()->getValue(),
            phone: $appointment->getPhone()->getValue(),
            city: $appointment->getCity(),
            country: $appointment->getCountry(),
            patientId: $appointment->getPatientId()?->getValue(),
            paymentVerified: $appointment->isPaymentVerified(),
            createdAt: InstantFormatter::toAtomUtc($appointment->getCreatedAt()),
            updatedAt: InstantFormatter::toAtomUtc($appointment->getUpdatedAt()),
            requesterTimezone: $appointment->getRequesterTimezone()?->getValue(),
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
            'patient_id' => $this->patientId,
            'payment_verified' => $this->paymentVerified,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'requester_timezone' => $this->requesterTimezone,
        ];
    }
}
