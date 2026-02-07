<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\Exception\DomainException;

final class InvalidTokenException extends DomainException
{
    public static function expired(): self
    {
        return new self(
            message: 'Token has expired.',
            errorCode: 'TOKEN_EXPIRED',
        );
    }

    public static function alreadyUsed(): self
    {
        return new self(
            message: 'Token has already been used.',
            errorCode: 'TOKEN_ALREADY_USED',
        );
    }

    public static function notFound(): self
    {
        return new self(
            message: 'Token not found.',
            errorCode: 'TOKEN_NOT_FOUND',
        );
    }
}
