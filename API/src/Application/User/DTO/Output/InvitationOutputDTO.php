<?php

declare(strict_types=1);

namespace App\Application\User\DTO\Output;

use App\Application\Shared\InstantFormatter;
use App\Domain\User\Entity\InvitationToken;
use DateTimeImmutable;

final readonly class InvitationOutputDTO
{
    public function __construct(
        public string $id,
        public string $email,
        public string $patientName,
        public string $status,
        public string $createdAt,
        public string $expiresAt,
    ) {
    }

    public static function fromEntity(InvitationToken $token, DateTimeImmutable $now): self
    {
        $status = match (true) {
            $token->isUsed() => 'used',
            $token->isRevoked() => 'revoked',
            $token->isExpired($now) => 'expired',
            default => 'pending',
        };

        return new self(
            id: $token->getId()->getValue(),
            email: $token->getEmail()->getValue(),
            patientName: $token->getPatientName(),
            status: $status,
            createdAt: InstantFormatter::toAtomUtc($token->getCreatedAt()),
            expiresAt: InstantFormatter::toAtomUtc($token->getExpiresAt()),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'patient_name' => $this->patientName,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
        ];
    }
}
