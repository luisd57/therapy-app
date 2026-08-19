<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Domain\User\Exception\InvalidTokenException;

trait MapsTokenErrorsTrait
{
    private function mapTokenErrorMessage(InvalidTokenException $invalidTokenException): string
    {
        return match ($invalidTokenException->getErrorCode()) {
            'TOKEN_EXPIRED' => 'Token has expired.',
            'TOKEN_ALREADY_USED' => 'Token has already been used.',
            'TOKEN_REVOKED' => 'Token has been revoked.',
            'TOKEN_NOT_FOUND' => 'Invalid token.',
            default => 'Invalid token.',
        };
    }
}
