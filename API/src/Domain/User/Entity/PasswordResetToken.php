<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\User\Id\TokenId;
use App\Domain\User\Id\UserId;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'password_reset_tokens')]
class PasswordResetToken
{
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isUsed = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $usedAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'token_id')]
        private readonly TokenId $id,
        #[ORM\Column(type: 'hashed_string', length: 255, unique: true)]
        private readonly string $token,
        #[ORM\Column(type: 'user_id')]
        private readonly UserId $userId,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
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
