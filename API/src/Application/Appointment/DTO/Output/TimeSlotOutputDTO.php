<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Output;

use App\Application\Shared\InstantFormatter;
use App\Domain\Appointment\ValueObject\TimeSlot;

final readonly class TimeSlotOutputDTO
{
    public function __construct(
        public string $startTime,
        public string $endTime,
        public int $durationMinutes,
    ) {
    }

    public static function fromValueObject(TimeSlot $timeSlot): self
    {
        return new self(
            startTime: InstantFormatter::toAtomUtc($timeSlot->getStartTime()),
            endTime: InstantFormatter::toAtomUtc($timeSlot->getEndTime()),
            durationMinutes: $timeSlot->getDurationMinutes(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'duration_minutes' => $this->durationMinutes,
        ];
    }
}
