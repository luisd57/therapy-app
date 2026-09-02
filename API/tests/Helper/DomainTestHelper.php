<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Id\AppointmentId;
use App\Domain\Appointment\Id\ExceptionId;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Enum\AppointmentStatus;
use App\Domain\Appointment\Enum\WeekDay;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\Entity\PasswordResetToken;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\ValueObject\Address;
use App\Domain\User\ValueObject\Timezone;
use App\Domain\User\Id\TokenId;
use App\Domain\User\Id\UserId;
use App\Domain\User\Enum\UserRole;
use DateTimeImmutable;
use DateTimeZone;

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
            now: new DateTimeImmutable(),
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
            now: new DateTimeImmutable(),
        );
    }

    public static function createActivePatient(
        ?UserId $id = null,
        string $email = 'patient@example.com',
        string $fullName = 'Test Patient',
        string $hashedPassword = 'hashed_password_123',
    ): User {
        $user = self::createPatient($id, $email, $fullName);
        $user->activate($hashedPassword, new DateTimeImmutable());
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

    /**
     * A relative offset combined with a wall-clock time ('+1 day 10:00') resolves
     * against the process timezone, which the suite deliberately sets to +14:00.
     * Pinning it to UTC keeps fixtures at a predictable instant so tests can assert
     * absolute times rather than only relative ones.
     */
    private static function defaultStartTime(): DateTimeImmutable
    {
        return new DateTimeImmutable('+1 day 10:00', new DateTimeZone('UTC'));
    }

    public static function createRequestedAppointment(
        ?AppointmentId $id = null,
        ?DateTimeImmutable $startTime = null,
        AppointmentModality $modality = AppointmentModality::ONLINE,
        string $fullName = 'John Doe',
        string $email = 'john@example.com',
        string $phone = '+1234567890',
        string $city = 'New York',
        string $country = 'USA',
        ?User $patient = null,
        ?Timezone $requesterTimezone = null,
    ): Appointment {
        return Appointment::request(
            id: $id ?? AppointmentId::generate(),
            timeSlot: TimeSlot::create($startTime ?? self::defaultStartTime(), 50),
            modality: $modality,
            fullName: $fullName,
            email: Email::fromString($email),
            phone: Phone::fromString($phone),
            city: $city,
            country: $country,
            now: new DateTimeImmutable(),
            patient: $patient,
            requesterTimezone: $requesterTimezone,
        );
    }

    public static function createConfirmedAppointment(
        ?AppointmentId $id = null,
        ?DateTimeImmutable $startTime = null,
        AppointmentModality $modality = AppointmentModality::ONLINE,
        string $fullName = 'John Doe',
        string $email = 'john@example.com',
        string $phone = '+1234567890',
        string $city = 'New York',
        string $country = 'USA',
        ?User $patient = null,
        ?Timezone $requesterTimezone = null,
    ): Appointment {
        return Appointment::reconstitute(
            id: $id ?? AppointmentId::generate(),
            timeSlot: TimeSlot::create($startTime ?? self::defaultStartTime(), 50),
            modality: $modality,
            status: AppointmentStatus::CONFIRMED,
            fullName: $fullName,
            email: Email::fromString($email),
            phone: Phone::fromString($phone),
            city: $city,
            country: $country,
            patient: $patient,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
            requesterTimezone: $requesterTimezone,
        );
    }

    public static function createScheduleBlock(
        User $therapist,
        WeekDay $dayOfWeek = WeekDay::MONDAY,
        string $startTime = '09:00',
        string $endTime = '17:00',
    ): TherapistSchedule {
        return TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: $therapist,
            dayOfWeek: $dayOfWeek,
            startTime: $startTime,
            endTime: $endTime,
            now: new DateTimeImmutable(),
        );
    }

    public static function createScheduleException(
        User $therapist,
        ?DateTimeImmutable $startDateTime = null,
        ?DateTimeImmutable $endDateTime = null,
        string $reason = 'Away',
    ): ScheduleException {
        $start = $startDateTime ?? new DateTimeImmutable('+2 days 09:00', new DateTimeZone('UTC'));

        return ScheduleException::create(
            id: ExceptionId::generate(),
            therapist: $therapist,
            startDateTime: $start,
            endDateTime: $endDateTime ?? $start->modify('+4 hours'),
            now: new DateTimeImmutable(),
            practiceTimeZone: new DateTimeZone('UTC'),
            reason: $reason,
        );
    }

    public static function createValidInvitation(
        ?TokenId $id = null,
        string $token = 'valid-token-string',
        string $email = 'patient@example.com',
        string $patientName = 'Test Patient',
        ?User $invitedBy = null,
        int $ttlSeconds = 86400,
        ?DateTimeImmutable $now = null,
    ): InvitationToken {
        return InvitationToken::create(
            id: $id ?? TokenId::generate(),
            token: $token,
            email: Email::fromString($email),
            patientName: $patientName,
            invitedBy: $invitedBy ?? self::createTherapist(),
            ttlSeconds: $ttlSeconds,
            now: $now ?? new DateTimeImmutable(),
        );
    }

    public static function createExpiredInvitation(
        string $token = 'expired-token',
        string $email = 'expired@example.com',
        ?User $invitedBy = null,
    ): InvitationToken {
        return InvitationToken::reconstitute(
            id: TokenId::generate(),
            token: $token,
            email: Email::fromString($email),
            patientName: 'Expired Patient',
            invitedBy: $invitedBy ?? self::createTherapist(),
            isUsed: false,
            createdAt: new DateTimeImmutable('-2 hours'),
            expiresAt: new DateTimeImmutable('-1 hour'),
            usedAt: null,
        );
    }

    public static function createUsedInvitation(
        string $token = 'used-token',
        string $email = 'used@example.com',
        ?User $invitedBy = null,
    ): InvitationToken {
        return InvitationToken::reconstitute(
            id: TokenId::generate(),
            token: $token,
            email: Email::fromString($email),
            patientName: 'Used Patient',
            invitedBy: $invitedBy ?? self::createTherapist(),
            isUsed: true,
            createdAt: new DateTimeImmutable('-1 hour'),
            expiresAt: new DateTimeImmutable('+23 hours'),
            usedAt: new DateTimeImmutable('-30 minutes'),
        );
    }

    /**
     * Expires exactly at $expiresAt, for pinning the boundary isExpired compares on.
     */
    public static function createBoundaryInvitation(
        string $token = 'boundary-token',
        string $email = 'boundary@example.com',
        ?User $invitedBy = null,
        ?DateTimeImmutable $expiresAt = null,
    ): InvitationToken {
        $expiry = $expiresAt ?? new DateTimeImmutable();

        return InvitationToken::reconstitute(
            id: TokenId::generate(),
            token: $token,
            email: Email::fromString($email),
            patientName: 'Boundary Patient',
            invitedBy: $invitedBy ?? self::createTherapist(),
            isUsed: false,
            createdAt: $expiry->modify('-1 hour'),
            expiresAt: $expiry,
            usedAt: null,
        );
    }

    public static function createRevokedInvitation(
        ?TokenId $id = null,
        string $token = 'revoked-token',
        string $email = 'revoked@example.com',
        ?User $invitedBy = null,
    ): InvitationToken {
        return InvitationToken::reconstitute(
            id: $id ?? TokenId::generate(),
            token: $token,
            email: Email::fromString($email),
            patientName: 'Revoked Patient',
            invitedBy: $invitedBy ?? self::createTherapist(),
            isUsed: false,
            createdAt: new DateTimeImmutable('-1 hour'),
            expiresAt: new DateTimeImmutable('+23 hours'),
            usedAt: null,
            isRevoked: true,
            revokedAt: new DateTimeImmutable('-30 minutes'),
        );
    }

    public static function createValidPasswordResetToken(
        ?TokenId $id = null,
        string $token = 'valid-reset-token',
        ?User $user = null,
        int $ttlSeconds = 3600,
        ?DateTimeImmutable $now = null,
    ): PasswordResetToken {
        return PasswordResetToken::create(
            id: $id ?? TokenId::generate(),
            token: $token,
            user: $user ?? self::createActivePatient(),
            ttlSeconds: $ttlSeconds,
            now: $now ?? new DateTimeImmutable(),
        );
    }

    public static function createExpiredPasswordResetToken(
        string $token = 'expired-reset-token',
        ?User $user = null,
    ): PasswordResetToken {
        return PasswordResetToken::reconstitute(
            id: TokenId::generate(),
            token: $token,
            user: $user ?? self::createActivePatient(),
            isUsed: false,
            createdAt: new DateTimeImmutable('-2 hours'),
            expiresAt: new DateTimeImmutable('-1 hour'),
            usedAt: null,
        );
    }

    public static function createUsedPasswordResetToken(
        string $token = 'used-reset-token',
        ?User $user = null,
    ): PasswordResetToken {
        return PasswordResetToken::reconstitute(
            id: TokenId::generate(),
            token: $token,
            user: $user ?? self::createActivePatient(),
            isUsed: true,
            createdAt: new DateTimeImmutable('-1 hour'),
            expiresAt: new DateTimeImmutable('+30 minutes'),
            usedAt: new DateTimeImmutable('-30 minutes'),
        );
    }
}
