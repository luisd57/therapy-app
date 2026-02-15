<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Entity;

use App\Domain\Appointment\ValueObject\ExceptionId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\ValueObject\UserId;
use DateTimeImmutable;

class ScheduleException
{
    public function __construct(
        private readonly ExceptionId $id,
        private readonly UserId $therapistId,
        private readonly DateTimeImmutable $startDateTime,
        private readonly DateTimeImmutable $endDateTime,
        private readonly string $reason,
        private readonly bool $isAllDay,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        ExceptionId $id,
        UserId $therapistId,
        DateTimeImmutable $startDateTime,
        DateTimeImmutable $endDateTime,
        string $reason = '',
        bool $isAllDay = false,
    ): self {
        if ($endDateTime <= $startDateTime) {
            throw new \InvalidArgumentException('End date/time must be after start date/time.');
        }

        return new self(
            id: $id,
            therapistId: $therapistId,
            startDateTime: $startDateTime,
            endDateTime: $endDateTime,
            reason: trim($reason),
            isAllDay: $isAllDay,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function overlapsTimeSlot(TimeSlot $slot): bool
    {
        return $this->startDateTime < $slot->getEndTime()
            && $slot->getStartTime() < $this->endDateTime;
    }

    public function getId(): ExceptionId
    {
        return $this->id;
    }

    public function getTherapistId(): UserId
    {
        return $this->therapistId;
    }

    public function getStartDateTime(): DateTimeImmutable
    {
        return $this->startDateTime;
    }

    public function getEndDateTime(): DateTimeImmutable
    {
        return $this->endDateTime;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function isAllDay(): bool
    {
        return $this->isAllDay;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public static function reconstitute(
        ExceptionId $id,
        UserId $therapistId,
        DateTimeImmutable $startDateTime,
        DateTimeImmutable $endDateTime,
        string $reason,
        bool $isAllDay,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            therapistId: $therapistId,
            startDateTime: $startDateTime,
            endDateTime: $endDateTime,
            reason: $reason,
            isAllDay: $isAllDay,
            createdAt: $createdAt,
        );
    }
}
