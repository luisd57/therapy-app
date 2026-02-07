<?php

declare(strict_types=1);

namespace App\Application\User\DTO\Output;

use App\Domain\User\Entity\InvitationToken;

final readonly class InvitationDTO
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

    public static function fromEntity(InvitationToken $token): self
    {
        $status = match (true) {
            $token->isUsed() => 'used',
            $token->isExpired() => 'expired',
            default => 'pending',
        };

        return new self(
            id: $token->getId()->getValue(),
            email: $token->getEmail()->getValue(),
            patientName: $token->getPatientName(),
            status: $status,
            createdAt: $token->getCreatedAt()->format(\DateTimeInterface::ATOM),
            expiresAt: $token->getExpiresAt()->format(\DateTimeInterface::ATOM),
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
