<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Input;

final readonly class GetAvailableSlotsInputDTO
{
    public function __construct(
        public string $from,
        public string $to,
        public ?string $modality = null,
    ) {
    }
}
