<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Entity;

use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\Appointment\ValueObject\SlotLockId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'slot_locks')]
class SlotLock
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'slot_lock_id')]
        private readonly SlotLockId $id,
        #[ORM\Embedded(class: TimeSlot::class, columnPrefix: false)]
        private readonly TimeSlot $timeSlot,
        #[ORM\Column(type: Types::STRING, length: 20, enumType: AppointmentModality::class)]
        private readonly AppointmentModality $modality,
        #[ORM\Column(type: 'hashed_string', length: 255, unique: true)]
        private readonly string $lockToken,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
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
        return hash_equals($this->lockToken, $token);
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
