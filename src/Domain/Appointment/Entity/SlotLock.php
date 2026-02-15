<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Entity;

use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\Appointment\ValueObject\SlotLockId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use DateTimeImmutable;

class SlotLock
{
    public function __construct(
        private readonly SlotLockId $id,
        private readonly TimeSlot $timeSlot,
        private readonly AppointmentModality $modality,
        private readonly string $lockToken,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $expiresAt,
    ) {
    }

    public static function create(
        SlotLockId $id,
        TimeSlot $timeSlot,
        AppointmentModality $modality,
        string $lockToken,
        int $ttlSeconds,
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: $id,
            timeSlot: $timeSlot,
            modality: $modality,
            lockToken: $lockToken,
            createdAt: $now,
            expiresAt: $now->modify("+{$ttlSeconds} seconds"),
        );
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return !$this->isExpired();
    }

    public function matchesToken(string $token): bool
    {
        return $this->lockToken === $token;
    }

    public function getId(): SlotLockId
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

    public function getLockToken(): string
    {
        return $this->lockToken;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public static function reconstitute(
        SlotLockId $id,
        TimeSlot $timeSlot,
        AppointmentModality $modality,
        string $lockToken,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): self {
        return new self(
            id: $id,
            timeSlot: $timeSlot,
            modality: $modality,
            lockToken: $lockToken,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
        );
    }
}
