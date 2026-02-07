<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

enum UserRole: string
{
    case THERAPIST = 'ROLE_THERAPIST';
    case PATIENT = 'ROLE_PATIENT';

    public function isTherapist(): bool
    {
        return $this === self::THERAPIST;
    }

    public function isPatient(): bool
    {
        return $this === self::PATIENT;
    }

    public function getDisplayName(): string
    {
        return match ($this) {
            self::THERAPIST => 'Therapist',
            self::PATIENT => 'Patient',
        };
    }

    /**
     * @return array<string>
     */
    public function getSecurityRoles(): array
    {
        return match ($this) {
            self::THERAPIST => ['ROLE_THERAPIST', 'ROLE_USER'],
            self::PATIENT => ['ROLE_PATIENT', 'ROLE_USER'],
        };
    }
}
