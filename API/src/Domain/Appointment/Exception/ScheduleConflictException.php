<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Exception;

use App\Domain\Exception\DomainException;

final class ScheduleConflictException extends DomainException
{
    public function __construct(string $message = 'A schedule conflict was detected.')
    {
        parent::__construct(
            message: $message,
            errorCode: 'SCHEDULE_CONFLICT',
        );
    }

    public static function overlap(): self
    {
        return new self('The schedule block overlaps with an existing schedule entry.');
    }

    public static function scheduleNotFound(string $id): self
    {
        return new self("Schedule with ID {$id} not found.");
    }

    public static function exceptionNotFound(string $id): self
    {
        return new self("Schedule exception with ID {$id} not found.");
    }
}
