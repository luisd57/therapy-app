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
#[ORM\Index(columns: ['token'], name: 'idx_invitation_token')]
#[ORM\Index(columns: ['email'], name: 'idx_invitation_email')]
// Predates the revoke flow, so it covers two of the three parts of isValid().
// A revoked token is still filtered outside the index.
#[ORM\Index(columns: ['is_used', 'expires_at'], name: 'idx_invitation_valid')]
class InvitationToken
{
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isUsed = false;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $usedAt = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isRevoked = false;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

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
        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'sentInvitations')]
        #[ORM\JoinColumn(name: 'invited_by', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private readonly User $invitedBy,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private readonly DateTimeImmutable $expiresAt,
    ) {
    }

    public static function create(
        TokenId $id,
        string $token,
        Email $email,
        string $patientName,
        User $invitedBy,
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

        if ($this->isRevoked) {
            throw new \DomainException('Invitation token has been revoked.');
        }

        if ($this->isExpired($now)) {
            throw new \DomainException('Invitation token has expired.');
        }

        $this->isUsed = true;
        $this->usedAt = $now;
    }

    public function revoke(DateTimeImmutable $now): void
    {
        if ($this->isUsed) {
            throw new \DomainException('Invitation token has already been used.');
        }

        if ($this->isRevoked) {
            throw new \DomainException('Invitation token has already been revoked.');
        }

        $this->isRevoked = true;
        $this->revokedAt = $now;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt < $now;
    }

    public function isValid(DateTimeImmutable $now): bool
    {
        return !$this->isUsed && !$this->isRevoked && !$this->isExpired($now);
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

    public function getInvitedBy(): User
    {
        return $this->invitedBy;
    }

    public function getInvitedById(): UserId
    {
        return $this->invitedBy->getId();
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

    public function isRevoked(): bool
    {
        return $this->isRevoked;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public static function reconstitute(
        TokenId $id,
        string $token,
        Email $email,
        string $patientName,
        User $invitedBy,
        bool $isUsed,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $usedAt,
        bool $isRevoked = false,
        ?DateTimeImmutable $revokedAt = null,
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
        $invitation->isRevoked = $isRevoked;
        $invitation->revokedAt = $revokedAt;

        return $invitation;
    }
}
