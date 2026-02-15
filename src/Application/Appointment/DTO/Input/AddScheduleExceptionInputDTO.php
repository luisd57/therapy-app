<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Input;

final readonly class AddScheduleExceptionInputDTO
{
    public function __construct(
        public string $therapistId,
        public string $startDateTime,
        public string $endDateTime,
        public string $reason = '',
        public bool $isAllDay = false,
    ) {
    }
}
