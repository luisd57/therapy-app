<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Exception;

use App\Domain\Exception\DomainException;

final class AppointmentNotFoundException extends DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(
            message: "Appointment with ID {$id} not found.",
            errorCode: 'APPOINTMENT_NOT_FOUND',
        );
    }
}
