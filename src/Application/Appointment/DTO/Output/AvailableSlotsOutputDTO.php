<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Output;

final readonly class AvailableSlotsOutputDTO
{
    /**
     * @param array<string, array<TimeSlotOutputDTO>> $slotsByDate
     */
    public function __construct(
        public string $from,
        public string $to,
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
            'from' => $this->from,
            'to' => $this->to,
            'modality' => $this->modality,
            'slots_by_date' => $result,
            'total_slots' => $this->totalSlots,
        ];
    }
}
