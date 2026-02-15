<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Input;

final readonly class UpdateTherapistScheduleInputDTO
{
    public function __construct(
        public string $scheduleId,
        public string $therapistId,
        public int $dayOfWeek,
        public string $startTime,
        public string $endTime,
        public bool $supportsOnline = true,
        public bool $supportsInPerson = true,
    ) {
    }
}
