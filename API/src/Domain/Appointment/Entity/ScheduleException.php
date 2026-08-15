<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Entity;

use App\Domain\Appointment\Id\ExceptionId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Id\UserId;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'schedule_exceptions')]
#[ORM\Index(columns: ['therapist_id', 'start_date_time', 'end_date_time'], name: 'idx_exception_range')]
class ScheduleException
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'exception_id')]
        private readonly ExceptionId $id,
        #[ORM\Column(type: 'user_id')]
        private readonly UserId $therapistId,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private readonly DateTimeImmutable $startDateTime,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private readonly DateTimeImmutable $endDateTime,
        #[ORM\Column(type: Types::STRING, length: 500)]
        private readonly string $reason,
        #[ORM\Column(type: Types::BOOLEAN)]
        private readonly bool $isAllDay,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        ExceptionId $id,
        UserId $therapistId,
        DateTimeImmutable $startDateTime,
        DateTimeImmutable $endDateTime,
        DateTimeImmutable $now,
        DateTimeZone $practiceTimeZone,
        string $reason = '',
        bool $isAllDay = false,
    ): self {
        if ($endDateTime <= $startDateTime) {
            throw new \InvalidArgumentException('End date/time must be after start date/time.');
        }

        if ($isAllDay) {
            $startDateTime = self::dayStart($startDateTime, $practiceTimeZone);
            $endDateTime = self::nextDayStart($endDateTime, $practiceTimeZone);
        }

        return new self(
            id: $id,
            therapistId: $therapistId,
            startDateTime: $startDateTime,
            endDateTime: $endDateTime,
            reason: trim($reason),
            isAllDay: $isAllDay,
            createdAt: $now,
        );
    }

    /** Practice-local midnight opening the day this instant falls on. */
    private static function dayStart(DateTimeImmutable $instant, DateTimeZone $practiceTimeZone): DateTimeImmutable
    {
        return $instant->setTimezone($practiceTimeZone)
            ->setTime(0, 0)
            ->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Practice-local midnight at or after this instant. The range end is exclusive,
     * so an end already on midnight must not gain a day it never covered.
     */
    private static function nextDayStart(DateTimeImmutable $instant, DateTimeZone $practiceTimeZone): DateTimeImmutable
    {
        $dayStart = self::dayStart($instant, $practiceTimeZone);

        if ($dayStart == $instant) {
            return $dayStart;
        }

        // Adding the day in the practice zone keeps this calendar arithmetic. On a
        // UTC value it would be a flat 24 hours and drift across a DST change.
        return $dayStart->setTimezone($practiceTimeZone)
            ->modify('+1 day')
            ->setTimezone(new DateTimeZone('UTC'));
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
