<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\Exception\DomainException;

final class InvalidCredentialsException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Invalid credentials provided.',
            errorCode: 'INVALID_CREDENTIALS',
        );
    }
}
