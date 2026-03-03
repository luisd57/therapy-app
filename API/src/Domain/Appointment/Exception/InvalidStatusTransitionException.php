<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Exception;

use App\Domain\Appointment\Enum\AppointmentStatus;
use App\Domain\Exception\DomainException;

final class InvalidStatusTransitionException extends DomainException
{
    public function __construct(AppointmentStatus $from, AppointmentStatus $to)
    {
        parent::__construct(
            message: "Cannot transition from {$from->value} to {$to->value}.",
            errorCode: 'INVALID_STATUS_TRANSITION',
        );
    }
}
