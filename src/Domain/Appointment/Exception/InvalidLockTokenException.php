<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Exception;

use App\Domain\Exception\DomainException;

final class InvalidLockTokenException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'The slot lock token is invalid or has expired.',
            errorCode: 'INVALID_LOCK_TOKEN',
        );
    }
}
