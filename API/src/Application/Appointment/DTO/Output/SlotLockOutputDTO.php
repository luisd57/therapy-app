<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Output;

use App\Application\Shared\InstantFormatter;
use App\Domain\Appointment\Entity\SlotLock;

final readonly class SlotLockOutputDTO
{
    public function __construct(
        public string $lockToken,
        public string $slotStartTime,
        public string $slotEndTime,
        public string $expiresAt,
    ) {
    }

    public static function fromEntity(SlotLock $lock): self
    {
        return new self(
            lockToken: $lock->getLockToken(),
            slotStartTime: InstantFormatter::toAtomUtc($lock->getTimeSlot()->getStartTime()),
            slotEndTime: InstantFormatter::toAtomUtc($lock->getTimeSlot()->getEndTime()),
            expiresAt: InstantFormatter::toAtomUtc($lock->getExpiresAt()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lock_token' => $this->lockToken,
            'slot_start_time' => $this->slotStartTime,
            'slot_end_time' => $this->slotEndTime,
            'expires_at' => $this->expiresAt,
        ];
    }
}
