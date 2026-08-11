<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Service;

use DateTimeZone;

/**
 * How a schedule block is turned into bookable slots.
 * Duration and increment are separate so starts stay reachable: 19:00 is not a multiple of 90 from 13:30.
 */
final readonly class SlotGenerationRules
{
    private function __construct(
        public int $durationMinutes,
        public int $startIncrementMinutes,
        public DateTimeZone $practiceTimeZone,
    ) {
    }

    public static function create(
        int $durationMinutes,
        DateTimeZone $practiceTimeZone,
        ?int $startIncrementMinutes = null,
    ): self {
        if ($durationMinutes <= 0) {
            throw new \InvalidArgumentException('Slot duration must be positive.');
        }

        // Omitting the increment yields a plain back-to-back grid.
        $increment = $startIncrementMinutes ?? $durationMinutes;

        if ($increment <= 0) {
            throw new \InvalidArgumentException('Slot start increment must be positive.');
        }

        return new self($durationMinutes, $increment, $practiceTimeZone);
    }
}
