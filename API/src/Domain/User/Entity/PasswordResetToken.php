<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\User\ValueObject\TokenId;
use App\Domain\User\ValueObject\UserId;
use DateTimeImmutable;

class PasswordResetToken
{
    private bool $isUsed = false;
    private ?DateTimeImmutable $usedAt = null;

    public function __construct(
        private readonly TokenId $id,
        private readonly string $token,
        private readonly UserId $userId,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $expiresAt,
    ) {
    }

    public static function create(
        TokenId $id,
        string $token,
        UserId $userId,
        int $ttlSeconds,
    ): self {
        $now = new DateTimeImmutable();
        
        return new self(
            id: $id,
            token: $token,
            userId: $userId,
            createdAt: $now,
            expiresAt: $now->modify("+{$ttlSeconds} seconds"),
        );
    }

    public function use(): void
    {
        if ($this->isUsed) {
            throw new \DomainException('Password reset token has already been used.');
        }

        if ($this->isExpired()) {
            throw new \DomainException('Password reset token has expired.');
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

    public function getUserId(): UserId
    {
        return $this->userId;
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
        UserId $userId,
        bool $isUsed,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $usedAt,
    ): self {
        $resetToken = new self(
            id: $id,
            token: $token,
            userId: $userId,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
        );

        $resetToken->isUsed = $isUsed;
        $resetToken->usedAt = $usedAt;

        return $resetToken;
    }
}
