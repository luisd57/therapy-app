<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'schedule_exceptions')]
#[ORM\Index(columns: ['therapist_id', 'start_date_time', 'end_date_time'], name: 'idx_exception_range')]
class ScheduleExceptionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(type: Types::GUID)]
    private string $therapistId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $startDateTime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $endDateTime;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $reason = '';

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isAllDay = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function getId(): string
    {
        return $this->id;
    }

    public function getTherapistId(): string
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

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setTherapistId(string $therapistId): void
    {
        $this->therapistId = $therapistId;
    }

    public function setStartDateTime(DateTimeImmutable $startDateTime): void
    {
        $this->startDateTime = $startDateTime;
    }

    public function setEndDateTime(DateTimeImmutable $endDateTime): void
    {
        $this->endDateTime = $endDateTime;
    }

    public function setReason(string $reason): void
    {
        $this->reason = $reason;
    }

    public function setIsAllDay(bool $isAllDay): void
    {
        $this->isAllDay = $isAllDay;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
