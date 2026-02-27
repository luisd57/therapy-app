<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Output;

final readonly class NextAvailableWeekOutputDTO
{
    /**
     * @param array<string, array<TimeSlotOutputDTO>> $slotsByDate
     */
    public function __construct(
        public bool $found,
        public ?string $weekStart,
        public ?string $weekEnd,
        public ?string $modality,
        public array $slotsByDate,
        public int $totalSlots,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->slotsByDate as $date => $slots) {
            $result[$date] = array_map(fn (TimeSlotOutputDTO $slot) => $slot->toArray(), $slots);
        }

        return [
            'found' => $this->found,
            'week_start' => $this->weekStart,
            'week_end' => $this->weekEnd,
            'modality' => $this->modality,
            'slots_by_date' => $result,
            'total_slots' => $this->totalSlots,
        ];
    }
}
