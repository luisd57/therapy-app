<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Entity;

use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Enum\WeekDay;
use App\Domain\User\Id\UserId;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'therapist_schedules')]
#[ORM\Index(columns: ['therapist_id', 'day_of_week'], name: 'idx_schedule_therapist_day')]
class TherapistSchedule
{
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'schedule_id')]
        private readonly ScheduleId $id,
        #[ORM\Column(type: 'user_id')]
        private readonly UserId $therapistId,
        #[ORM\Column(type: Types::SMALLINT, enumType: WeekDay::class)]
        private WeekDay $dayOfWeek,
        #[ORM\Column(type: Types::STRING, length: 5)]
        private string $startTime,
        #[ORM\Column(type: Types::STRING, length: 5)]
        private string $endTime,
        #[ORM\Column(type: Types::BOOLEAN)]
        private bool $supportsOnline,
        #[ORM\Column(type: Types::BOOLEAN)]
        private bool $supportsInPerson,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
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
