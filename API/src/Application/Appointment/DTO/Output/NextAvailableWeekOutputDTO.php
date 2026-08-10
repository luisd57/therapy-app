<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Output;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Week bounds are instants, not calendar dates: the week is the therapist's,
 * and a client in another zone needs to know exactly where it starts and ends
 * to lay it out against its own days. weekEnd is exclusive.
 */
final readonly class NextAvailableWeekOutputDTO
{
    /**
     * @param ArrayCollection<int, TimeSlotOutputDTO> $slots
     */
    public function __construct(
        public bool $found,
        public ?string $weekStart,
        public ?string $weekEnd,
        public ?string $modality,
        public string $practiceTimezone,
        public ArrayCollection $slots,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'found' => $this->found,
            'week_start' => $this->weekStart,
            'week_end' => $this->weekEnd,
            'modality' => $this->modality,
            'practice_timezone' => $this->practiceTimezone,
            'slots' => array_values(
                $this->slots->map(fn (TimeSlotOutputDTO $slot) => $slot->toArray())->toArray(),
            ),
            'total_slots' => $this->slots->count(),
        ];
    }
}
