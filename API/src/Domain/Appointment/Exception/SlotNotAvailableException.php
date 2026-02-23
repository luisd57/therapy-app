<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Exception;

use App\Domain\Exception\DomainException;

final class SlotNotAvailableException extends DomainException
{
    public function __construct(string $message = 'The requested time slot is no longer available.')
    {
        parent::__construct(
            message: $message,
            errorCode: 'SLOT_NOT_AVAILABLE',
        );
    }

    public static function alreadyLocked(): self
    {
        return new self('This time slot is currently being reserved by another user.');
    }
}
