<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Output;

use App\Domain\Appointment\ValueObject\TimeSlot;
use DateTimeInterface;

final readonly class TimeSlotOutputDTO
{
    public function __construct(
        public string $startTime,
        public string $endTime,
        public int $durationMinutes,
    ) {
    }

    public static function fromValueObject(TimeSlot $slot): self
    {
        return new self(
            startTime: $slot->getStartTime()->format(DateTimeInterface::ATOM),
            endTime: $slot->getEndTime()->format(DateTimeInterface::ATOM),
            durationMinutes: $slot->getDurationMinutes(),
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
