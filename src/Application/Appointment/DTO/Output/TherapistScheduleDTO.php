<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Output;

use App\Domain\Appointment\Entity\TherapistSchedule;

final readonly class TherapistScheduleDTO
{
    public function __construct(
        public string $id,
        public int $dayOfWeek,
        public string $dayName,
        public string $startTime,
        public string $endTime,
        public bool $supportsOnline,
        public bool $supportsInPerson,
        public bool $isActive,
    ) {
    }

    public static function fromEntity(TherapistSchedule $schedule): self
    {
        return new self(
            id: $schedule->getId()->getValue(),
            dayOfWeek: $schedule->getDayOfWeek()->value,
            dayName: $schedule->getDayOfWeek()->getDisplayName(),
            startTime: $schedule->getStartTime(),
            endTime: $schedule->getEndTime(),
            supportsOnline: $schedule->isSupportsOnline(),
            supportsInPerson: $schedule->isSupportsInPerson(),
            isActive: $schedule->isActive(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->dayOfWeek,
            'day_name' => $this->dayName,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'supports_online' => $this->supportsOnline,
            'supports_in_person' => $this->supportsInPerson,
            'is_active' => $this->isActive,
        ];
    }
}
