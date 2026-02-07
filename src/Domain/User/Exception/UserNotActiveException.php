<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\Exception\DomainException;

final class UserNotActiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'User account is not active.',
            errorCode: 'USER_NOT_ACTIVE',
        );
    }
}
