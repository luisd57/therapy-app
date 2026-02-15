<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Input;

final readonly class SetTherapistScheduleInputDTO
{
    public function __construct(
        public string $therapistId,
        public int $dayOfWeek,
        public string $startTime,
        public string $endTime,
        public bool $supportsOnline = true,
        public bool $supportsInPerson = true,
    ) {
    }
}
