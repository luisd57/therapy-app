<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\User\ValueObject\TokenId;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Email;
use DateTimeImmutable;

class InvitationToken
{
    private bool $isUsed = false;
    private ?DateTimeImmutable $usedAt = null;

    public function __construct(
        private readonly TokenId $id,
        private readonly string $token,
        private readonly Email $email,
        private readonly string $patientName,
        private readonly UserId $invitedBy,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $expiresAt,
    ) {
    }

    public static function create(
        TokenId $id,
        string $token,
        Email $email,
        string $patientName,
        UserId $invitedBy,
        int $ttlSeconds,
    ): self {
        $now = new DateTimeImmutable();
        
        return new self(
            id: $id,
            token: $token,
            email: $email,
            patientName: $patientName,
            invitedBy: $invitedBy,
            createdAt: $now,
            expiresAt: $now->modify("+{$ttlSeconds} seconds"),
        );
    }

    public function use(): void
    {
        if ($this->isUsed) {
            throw new \DomainException('Invitation token has already been used.');
        }

        if ($this->isExpired()) {
            throw new \DomainException('Invitation token has expired.');
        }

        $this->isUsed = true;
        $this->usedAt = new DateTimeImmutable();
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return !$this->isUsed && !$this->isExpired();
    }

    // Getters
    public function getId(): TokenId
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getPatientName(): string
    {
        return $this->patientName;
    }

    public function getInvitedBy(): UserId
    {
        return $this->invitedBy;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isUsed(): bool
    {
        return $this->isUsed;
    }

    public function getUsedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }

    public static function reconstitute(
        TokenId $id,
        string $token,
        Email $email,
        string $patientName,
        UserId $invitedBy,
        bool $isUsed,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $usedAt,
    ): self {
        $invitation = new self(
            id: $id,
            token: $token,
            email: $email,
            patientName: $patientName,
            invitedBy: $invitedBy,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
        );

        $invitation->isUsed = $isUsed;
        $invitation->usedAt = $usedAt;

        return $invitation;
    }
}
