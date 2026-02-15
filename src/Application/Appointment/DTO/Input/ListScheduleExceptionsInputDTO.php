<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Input;

final readonly class ListScheduleExceptionsInputDTO
{
    public function __construct(
        public string $therapistId,
        public string $from,
        public string $to,
    ) {
    }
}
