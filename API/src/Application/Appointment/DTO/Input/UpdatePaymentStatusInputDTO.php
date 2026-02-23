<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Input;

final readonly class UpdatePaymentStatusInputDTO
{
    public function __construct(
        public string $appointmentId,
        public bool $paymentVerified,
    ) {
    }
}
