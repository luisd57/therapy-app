<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Exception;

use App\Domain\Exception\DomainException;

final class ScheduleConflictException extends DomainException
{
    public function __construct(string $message = 'A schedule conflict was detected.', string $errorCode = 'SCHEDULE_CONFLICT')
    {
        parent::__construct(
            message: $message,
            errorCode: $errorCode,
        );
    }

    public static function overlap(): self
    {
        return new self('The schedule block overlaps with an existing schedule entry.');
    }

    public static function scheduleNotFound(string $id): self
    {
        return new self("Schedule with ID {$id} not found.", 'SCHEDULE_NOT_FOUND');
    }

    public static function exceptionNotFound(string $id): self
    {
        return new self("Schedule exception with ID {$id} not found.", 'SCHEDULE_EXCEPTION_NOT_FOUND');
    }
}
