<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\Entity\PasswordResetToken;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\ValueObject\Address;
use App\Domain\User\ValueObject\TokenId;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use DateTimeImmutable;

final class DomainTestHelper
{
    public static function createTherapist(
        ?UserId $id = null,
        string $email = 'therapist@example.com',
        string $fullName = 'Dr. Test Therapist',
        string $hashedPassword = 'hashed_password_123',
    ): User {
        return User::createTherapist(
            id: $id ?? UserId::generate(),
            email: Email::fromString($email),
            fullName: $fullName,
            hashedPassword: $hashedPassword,
        );
    }

    public static function createPatient(
        ?UserId $id = null,
        string $email = 'patient@example.com',
        string $fullName = 'Test Patient',
    ): User {
        return User::createPatient(
            id: $id ?? UserId::generate(),
            email: Email::fromString($email),
            fullName: $fullName,
        );
    }

    public static function createActivePatient(
        ?UserId $id = null,
        string $email = 'patient@example.com',
        string $fullName = 'Test Patient',
        string $hashedPassword = 'hashed_password_123',
    ): User {
        $user = self::createPatient($id, $email, $fullName);
        $user->activate($hashedPassword);
        return $user;
    }

    public static function createReconstitutedTherapist(
        ?UserId $id = null,
        string $email = 'therapist@example.com',
        string $fullName = 'Dr. Test Therapist',
        string $hashedPassword = 'hashed_password_123',
    ): User {
        $userId = $id ?? UserId::generate();
        $now = new DateTimeImmutable();

        return User::reconstitute(
            id: $userId,
            email: Email::fromString($email),
            fullName: $fullName,
            role: UserRole::THERAPIST,
            password: $hashedPassword,
            phone: null,
            address: null,
            isActive: true,
            createdAt: $now,
            activatedAt: $now,
            updatedAt: $now,
        );
    }

    public static function createReconstitutedActivePatient(
        ?UserId $id = null,
        string $email = 'patient@example.com',
        string $fullName = 'Test Patient',
        string $hashedPassword = 'hashed_password_123',
    ): User {
        $userId = $id ?? UserId::generate();
        $now = new DateTimeImmutable();

        return User::reconstitute(
            id: $userId,
            email: Email::fromString($email),
            fullName: $fullName,
            role: UserRole::PATIENT,
            password: $hashedPassword,
            phone: null,
            address: null,
            isActive: true,
            createdAt: $now,
            activatedAt: $now,
            updatedAt: $now,
        );
    }

    public static function createReconstitutedInactivePatient(
        ?UserId $id = null,
        string $email = 'inactive@example.com',
        string $fullName = 'Inactive Patient',
    ): User {
        $userId = $id ?? UserId::generate();
        $now = new DateTimeImmutable();

        return User::reconstitute(
            id: $userId,
            email: Email::fromString($email),
            fullName: $fullName,
            role: UserRole::PATIENT,
            password: null,
            phone: null,
            address: null,
            isActive: false,
            createdAt: $now,
            activatedAt: null,
            updatedAt: $now,
        );
    }

    public static function createValidInvitation(
        ?TokenId $id = null,
        string $token = 'valid-token-string',
        string $email = 'patient@example.com',
        string $patientName = 'Test Patient',
        ?UserId $invitedBy = null,
        int $ttlSeconds = 86400,
    ): InvitationToken {
        return InvitationToken::create(
            id: $id ?? TokenId::generate(),
            token: $token,
            email: Email::fromString($email),
            patientName: $patientName,
            invitedBy: $invitedBy ?? UserId::generate(),
            ttlSeconds: $ttlSeconds,
        );
    }

    public static function createExpiredInvitation(
        string $token = 'expired-token',
        string $email = 'expired@example.com',
    ): InvitationToken {
        return InvitationToken::reconstitute(
            id: TokenId::generate(),
            token: $token,
            email: Email::fromString($email),
            patientName: 'Expired Patient',
            invitedBy: UserId::generate(),
            isUsed: false,
            createdAt: new DateTimeImmutable('-2 hours'),
            expiresAt: new DateTimeImmutable('-1 hour'),
            usedAt: null,
        );
    }

    public static function createUsedInvitation(
        string $token = 'used-token',
        string $email = 'used@example.com',
    ): InvitationToken {
        return InvitationToken::reconstitute(
            id: TokenId::generate(),
            token: $token,
            email: Email::fromString($email),
            patientName: 'Used Patient',
            invitedBy: UserId::generate(),
            isUsed: true,
            createdAt: new DateTimeImmutable('-1 hour'),
            expiresAt: new DateTimeImmutable('+23 hours'),
            usedAt: new DateTimeImmutable('-30 minutes'),
        );
    }

    public static function createBoundaryInvitation(
        string $token = 'boundary-token',
        string $email = 'boundary@example.com',
    ): InvitationToken {
        $now = new DateTimeImmutable();

        return InvitationToken::reconstitute(
            id: TokenId::generate(),
            token: $token,
            email: Email::fromString($email),
            patientName: 'Boundary Patient',
            invitedBy: UserId::generate(),
            isUsed: false,
            createdAt: new DateTimeImmutable('-1 hour'),
            expiresAt: $now,
            usedAt: null,
        );
    }

    public static function createValidPasswordResetToken(
        ?TokenId $id = null,
        string $token = 'valid-reset-token',
        ?UserId $userId = null,
        int $ttlSeconds = 3600,
    ): PasswordResetToken {
        return PasswordResetToken::create(
            id: $id ?? TokenId::generate(),
            token: $token,
            userId: $userId ?? UserId::generate(),
            ttlSeconds: $ttlSeconds,
        );
    }

    public static function createExpiredPasswordResetToken(
        string $token = 'expired-reset-token',
        ?UserId $userId = null,
    ): PasswordResetToken {
        return PasswordResetToken::reconstitute(
            id: TokenId::generate(),
            token: $token,
            userId: $userId ?? UserId::generate(),
            isUsed: false,
            createdAt: new DateTimeImmutable('-2 hours'),
            expiresAt: new DateTimeImmutable('-1 hour'),
            usedAt: null,
        );
    }

    public static function createUsedPasswordResetToken(
        string $token = 'used-reset-token',
        ?UserId $userId = null,
    ): PasswordResetToken {
        return PasswordResetToken::reconstitute(
            id: TokenId::generate(),
            token: $token,
            userId: $userId ?? UserId::generate(),
            isUsed: true,
            createdAt: new DateTimeImmutable('-1 hour'),
            expiresAt: new DateTimeImmutable('+30 minutes'),
            usedAt: new DateTimeImmutable('-30 minutes'),
        );
    }
}
