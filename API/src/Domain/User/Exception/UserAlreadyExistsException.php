<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\Exception\DomainException;

final class UserAlreadyExistsException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'A user with this email already exists.',
            errorCode: 'USER_ALREADY_EXISTS',
        );
    }
}
