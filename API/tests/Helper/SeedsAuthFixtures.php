<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;

/**
 * Persists the auth fixtures the /api/auth controller tests share.
 *
 * Credentials match the defaults in ApiTestCase, so a seeded user can be logged in with them.
 */
trait SeedsAuthFixtures
{
    protected const THERAPIST_EMAIL = 'therapist@test.com';
    protected const THERAPIST_PASSWORD = 'Password1!';
    protected const PATIENT_EMAIL = 'patient@test.com';
    protected const PATIENT_PASSWORD = 'Patient1!';

    protected function seedTherapist(): void
    {
        $hasher = self::getContainer()->get(PasswordHasherInterface::class);

        self::getContainer()->get(UserRepositoryInterface::class)->save(
            DomainTestHelper::createTherapist(
                email: self::THERAPIST_EMAIL,
                fullName: 'Test Therapist',
                hashedPassword: $hasher->hash(self::THERAPIST_PASSWORD),
            ),
        );
    }

    protected function seedActivatedPatient(): void
    {
        $hasher = self::getContainer()->get(PasswordHasherInterface::class);

        self::getContainer()->get(UserRepositoryInterface::class)->save(
            DomainTestHelper::createActivePatient(
                email: self::PATIENT_EMAIL,
                fullName: 'Test Patient',
                hashedPassword: $hasher->hash(self::PATIENT_PASSWORD),
            ),
        );
    }

    protected function seedInvitation(): InvitationToken
    {
        // Randomised so the unique constraints hold when several tests seed in one transaction
        $inviter = DomainTestHelper::createTherapist(
            email: 'inviter-' . bin2hex(random_bytes(4)) . '@test.com',
            fullName: 'Inviter Therapist',
        );
        self::getContainer()->get(UserRepositoryInterface::class)->save($inviter);

        $invitation = DomainTestHelper::createValidInvitation(
            token: 'test-invitation-token-' . bin2hex(random_bytes(8)),
            email: 'newpatient@test.com',
            patientName: 'New Patient',
            invitedBy: $inviter,
        );
        self::getContainer()->get(InvitationTokenRepositoryInterface::class)->save($invitation);

        return $invitation;
    }
}
