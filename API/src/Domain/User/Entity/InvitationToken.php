<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\User\ValueObject\Email;
use App\Domain\User\Id\TokenId;
use App\Domain\User\Id\UserId;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invitation_tokens')]
class InvitationToken
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
        #[ORM\Column(type: 'email', length: 255)]
        private readonly Email $email,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private readonly string $patientName,
        #[ORM\Column(type: 'user_id')]
        private readonly UserId $invitedBy,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
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
        DateTimeImmutable $now,
    ): self {
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

    public function use(DateTimeImmutable $now): void
    {
        if ($this->isUsed) {
            throw new \DomainException('Invitation token has already been used.');
        }

        if ($this->isExpired($now)) {
            throw new \DomainException('Invitation token has expired.');
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
