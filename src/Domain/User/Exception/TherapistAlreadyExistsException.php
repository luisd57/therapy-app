<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\Exception\DomainException;

final class TherapistAlreadyExistsException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'A therapist account already exists in the system.',
            errorCode: 'THERAPIST_ALREADY_EXISTS',
        );
    }
}
