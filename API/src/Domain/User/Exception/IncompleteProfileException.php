<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\Exception\DomainException;

final class IncompleteProfileException extends DomainException
{
    public function __construct(string $message = 'Patient profile is incomplete. Phone and address are required to book an appointment.')
    {
        parent::__construct(
            message: $message,
            errorCode: 'INCOMPLETE_PROFILE',
        );
    }
}
