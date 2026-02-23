<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Appointment\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'slot_locks')]
#[ORM\UniqueConstraint(name: 'UNIQ_slot_lock_token', columns: ['lock_token'])]
#[ORM\Index(columns: ['start_time', 'end_time', 'expires_at'], name: 'idx_lock_time_range')]
class SlotLockEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $startTime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $endTime;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $modality;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $lockToken;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $expiresAt;

    public function getId(): string
    {
        return $this->id;
    }

    public function getStartTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndTime(): DateTimeImmutable
    {
        return $this->endTime;
    }

    public function getModality(): string
    {
        return $this->modality;
    }

    public function getLockToken(): string
    {
        return $this->lockToken;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setStartTime(DateTimeImmutable $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function setEndTime(DateTimeImmutable $endTime): void
    {
        $this->endTime = $endTime;
    }

    public function setModality(string $modality): void
    {
        $this->modality = $modality;
    }

    public function setLockToken(string $lockToken): void
    {
        $this->lockToken = $lockToken;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }
}
