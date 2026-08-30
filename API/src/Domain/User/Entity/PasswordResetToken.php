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
#[ORM\Index(columns: ['token'], name: 'idx_password_reset_token')]
#[ORM\Index(columns: ['is_used', 'expires_at'], name: 'idx_password_reset_valid')]
class PasswordResetToken
{
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isUsed = false;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $usedAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'token_id')]
        private readonly TokenId $id,
        #[ORM\Column(type: 'hashed_string', length: 255, unique: true)]
        private readonly string $token,
        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'passwordResetTokens')]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private readonly User $user,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private readonly DateTimeImmutable $expiresAt,
    ) {
    }

    public static function create(
        TokenId $id,
        string $token,
        User $user,
        int $ttlSeconds,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            token: $token,
            user: $user,
            createdAt: $now,
            expiresAt: $now->modify("+{$ttlSeconds} seconds"),
        );
    }

    public function use(DateTimeImmutable $now): void
    {
        if ($this->isUsed) {
            throw new \DomainException('Password reset token has already been used.');
        }

        if ($this->isExpired($now)) {
            throw new \DomainException('Password reset token has expired.');
        }

        $this->isUsed = true;
        $this->usedAt = $now;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt < $now;
    }

    public function isValid(DateTimeImmutable $now): bool
    {
        return !$this->isUsed && !$this->isExpired($now);
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function getUserId(): UserId
    {
        return $this->user->getId();
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
        User $user,
        bool $isUsed,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $usedAt,
    ): self {
        $resetToken = new self(
            id: $id,
            token: $token,
            user: $user,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
        );

        $resetToken->isUsed = $isUsed;
        $resetToken->usedAt = $usedAt;

        return $resetToken;
    }
}
