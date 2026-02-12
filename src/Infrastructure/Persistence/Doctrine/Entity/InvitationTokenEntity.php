<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invitation_tokens')]
#[ORM\Index(columns: ['token'], name: 'idx_invitation_token')]
#[ORM\Index(columns: ['email'], name: 'idx_invitation_email')]
#[ORM\Index(columns: ['is_used', 'expires_at'], name: 'idx_invitation_valid')]
class InvitationTokenEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $token;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $patientName;

    #[ORM\Column(type: Types::GUID)]
    private string $invitedBy;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isUsed = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $usedAt = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPatientName(): string
    {
        return $this->patientName;
    }

    public function getInvitedBy(): string
    {
        return $this->invitedBy;
    }

    public function isUsed(): bool
    {
        return $this->isUsed;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getUsedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setPatientName(string $patientName): void
    {
        $this->patientName = $patientName;
    }

    public function setInvitedBy(string $invitedBy): void
    {
        $this->invitedBy = $invitedBy;
    }

    public function setIsUsed(bool $isUsed): void
    {
        $this->isUsed = $isUsed;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function setUsedAt(?DateTimeImmutable $usedAt): void
    {
        $this->usedAt = $usedAt;
    }
}