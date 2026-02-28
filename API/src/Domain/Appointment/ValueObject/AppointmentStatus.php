<?php

declare(strict_types=1);

namespace App\Domain\Appointment\ValueObject;

enum AppointmentStatus: string
{
    case REQUESTED = 'REQUESTED';
    case CONFIRMED = 'CONFIRMED';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::REQUESTED => in_array($target, [self::CONFIRMED, self::CANCELLED], true),
            self::CONFIRMED => in_array($target, [self::COMPLETED, self::CANCELLED], true),
            self::COMPLETED, self::CANCELLED => false,
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::COMPLETED || $this === self::CANCELLED;
    }

    public function blocksSlot(): bool
    {
        return $this === self::CONFIRMED;
    }

    public function getDisplayName(): string
    {
        return match ($this) {
            self::REQUESTED => 'Requested',
            self::CONFIRMED => 'Confirmed',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }
}
