<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Entity;

use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\Appointment\ValueObject\ScheduleId;
use App\Domain\Appointment\ValueObject\WeekDay;
use App\Domain\User\ValueObject\UserId;
use DateTimeImmutable;

class TherapistSchedule
{
    private bool $isActive = true;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly ScheduleId $id,
        private readonly UserId $therapistId,
        private WeekDay $dayOfWeek,
        private string $startTime,
        private string $endTime,
        private bool $supportsOnline,
        private bool $supportsInPerson,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
    }

    public static function create(
        ScheduleId $id,
        UserId $therapistId,
        WeekDay $dayOfWeek,
        string $startTime,
        string $endTime,
        bool $supportsOnline = true,
        bool $supportsInPerson = true,
    ): self {
        self::validateTimeFormat($startTime);
        self::validateTimeFormat($endTime);

        if ($startTime >= $endTime) {
            throw new \InvalidArgumentException('Start time must be before end time.');
        }

        return new self(
            id: $id,
            therapistId: $therapistId,
            dayOfWeek: $dayOfWeek,
            startTime: $startTime,
            endTime: $endTime,
            supportsOnline: $supportsOnline,
            supportsInPerson: $supportsInPerson,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function update(
        WeekDay $dayOfWeek,
        string $startTime,
        string $endTime,
        bool $supportsOnline,
        bool $supportsInPerson,
    ): void {
        self::validateTimeFormat($startTime);
        self::validateTimeFormat($endTime);

        if ($startTime >= $endTime) {
            throw new \InvalidArgumentException('Start time must be before end time.');
        }

        $this->dayOfWeek = $dayOfWeek;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->supportsOnline = $supportsOnline;
        $this->supportsInPerson = $supportsInPerson;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function supportsModality(AppointmentModality $modality): bool
    {
        return match ($modality) {
            AppointmentModality::ONLINE => $this->supportsOnline,
            AppointmentModality::IN_PERSON => $this->supportsInPerson,
        };
    }

    public function getId(): ScheduleId
    {
        return $this->id;
    }

    public function getTherapistId(): UserId
    {
        return $this->therapistId;
    }

    public function getDayOfWeek(): WeekDay
    {
        return $this->dayOfWeek;
    }

    public function getStartTime(): string
    {
        return $this->startTime;
    }

    public function getEndTime(): string
    {
        return $this->endTime;
    }

    public function isSupportsOnline(): bool
    {
        return $this->supportsOnline;
    }

    public function isSupportsInPerson(): bool
    {
        return $this->supportsInPerson;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public static function reconstitute(
        ScheduleId $id,
        UserId $therapistId,
        WeekDay $dayOfWeek,
        string $startTime,
        string $endTime,
        bool $supportsOnline,
        bool $supportsInPerson,
        bool $isActive,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        $schedule = new self(
            id: $id,
            therapistId: $therapistId,
            dayOfWeek: $dayOfWeek,
            startTime: $startTime,
            endTime: $endTime,
            supportsOnline: $supportsOnline,
            supportsInPerson: $supportsInPerson,
            createdAt: $createdAt,
        );

        $schedule->isActive = $isActive;
        $schedule->updatedAt = $updatedAt;

        return $schedule;
    }

    private static function validateTimeFormat(string $time): void
    {
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new \InvalidArgumentException("Invalid time format: {$time}. Expected HH:MM.");
        }

        [$hours, $minutes] = explode(':', $time);
        if ((int) $hours > 23 || (int) $minutes > 59) {
            throw new \InvalidArgumentException("Invalid time value: {$time}.");
        }
    }
}
