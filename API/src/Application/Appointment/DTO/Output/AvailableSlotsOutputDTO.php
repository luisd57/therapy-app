<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Output;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Slots are a flat list of instants, deliberately not grouped by date.
 *
 * Grouping server-side can only use the practice's calendar, which is the wrong
 * calendar for everyone outside Venezuela — a Friday 19:00 slot in Caracas is
 * already Saturday in Madrid. Clients bucket by their viewer's day instead.
 */
final readonly class AvailableSlotsOutputDTO
{
    /**
     * @param ArrayCollection<int, TimeSlotOutputDTO> $slots
     */
    public function __construct(
        public string $from,
        public string $to,
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
            'from' => $this->from,
            'to' => $this->to,
            'modality' => $this->modality,
            'practice_timezone' => $this->practiceTimezone,
            'slots' => array_values(
                $this->slots->map(fn (TimeSlotOutputDTO $slot) => $slot->toArray())->toArray(),
            ),
            'total_slots' => $this->slots->count(),
        ];
    }
}
