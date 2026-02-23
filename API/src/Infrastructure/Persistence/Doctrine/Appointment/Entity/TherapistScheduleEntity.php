<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Appointment\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'therapist_schedules')]
#[ORM\Index(columns: ['therapist_id', 'day_of_week'], name: 'idx_schedule_therapist_day')]
class TherapistScheduleEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(type: Types::GUID)]
    private string $therapistId;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $dayOfWeek;

    #[ORM\Column(type: Types::STRING, length: 5)]
    private string $startTime;

    #[ORM\Column(type: Types::STRING, length: 5)]
    private string $endTime;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $supportsOnline = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $supportsInPerson = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function getId(): string
    {
        return $this->id;
    }

    public function getTherapistId(): string
    {
        return $this->therapistId;
    }

    public function getDayOfWeek(): int
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

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setTherapistId(string $therapistId): void
    {
        $this->therapistId = $therapistId;
    }

    public function setDayOfWeek(int $dayOfWeek): void
    {
        $this->dayOfWeek = $dayOfWeek;
    }

    public function setStartTime(string $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function setEndTime(string $endTime): void
    {
        $this->endTime = $endTime;
    }

    public function setSupportsOnline(bool $supportsOnline): void
    {
        $this->supportsOnline = $supportsOnline;
    }

    public function setSupportsInPerson(bool $supportsInPerson): void
    {
        $this->supportsInPerson = $supportsInPerson;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
