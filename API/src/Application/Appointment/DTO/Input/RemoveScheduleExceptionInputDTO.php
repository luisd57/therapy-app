<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Input;

final readonly class RemoveScheduleExceptionInputDTO
{
    public function __construct(
        public string $exceptionId,
        public string $therapistId,
    ) {
    }
}
