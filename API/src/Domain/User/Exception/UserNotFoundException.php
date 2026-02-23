<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\Exception\DomainException;

final class UserNotFoundException extends DomainException
{
    public function __construct(string $identifier)
    {
        parent::__construct(
            message: "User not found: {$identifier}",
            errorCode: 'USER_NOT_FOUND',
        );
    }
}
