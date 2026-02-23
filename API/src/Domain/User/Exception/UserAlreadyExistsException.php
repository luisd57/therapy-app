<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\Exception\DomainException;

final class UserAlreadyExistsException extends DomainException
{
    public function __construct(string $email)
    {
        parent::__construct(
            message: "User with email {$email} already exists.",
            errorCode: 'USER_ALREADY_EXISTS',
        );
    }
}
