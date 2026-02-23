<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Input;

final readonly class LockSlotInputDTO
{
    public function __construct(
        public string $slotStartTime,
        public string $modality,
    ) {
    }
}
