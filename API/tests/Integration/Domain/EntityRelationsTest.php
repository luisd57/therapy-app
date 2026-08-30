<?php

declare(strict_types=1);

namespace App\Tests\Integration\Domain;

use App\Application\Appointment\DTO\Output\AppointmentOutputDTO;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;

/**
 * The guarantees the ManyToOne/OneToMany mappings are supposed to buy, and the one
 * they put at risk. See ADR-0007.
 */
final class EntityRelationsTest extends IntegrationTestCase
{
    public function testReadingAPatientIdDoesNotLoadThePatient(): void
    {
        $userRepository = self::getContainer()->get(UserRepositoryInterface::class);
        $appointmentRepository = self::getContainer()->get(AppointmentRepositoryInterface::class);

        $patient = DomainTestHelper::createActivePatient(email: 'proxy-' . bin2hex(random_bytes(4)) . '@test.com');
        $userRepository->save($patient);

        for ($index = 0; $index < 3; $index++) {
            $appointmentRepository->save(DomainTestHelper::createRequestedAppointment(patient: $patient));
        }

        $this->entityManager->clear();

        $appointments = $appointmentRepository->findAllPaginated(0, 20);
        $unitOfWork = $this->entityManager->getUnitOfWork();

        // Mapping to the response DTO is the hot path. It reads the id and nothing else,
        // so every patient must still be an untouched proxy afterwards. If someone adds a
        // getPatient()->getFullName() to the DTO this fails, which is the point: that call
        // is one query per row and reads like a plain getter.
        foreach ($appointments as $appointment) {
            AppointmentOutputDTO::fromEntity($appointment);
        }

        $linked = 0;

        foreach ($appointments as $appointment) {
            $loadedPatient = $appointment->getPatient();

            if ($loadedPatient === null) {
                continue;
            }

            $linked++;
            $this->assertTrue(
                $unitOfWork->isUninitializedObject($loadedPatient),
                'Building AppointmentOutputDTO initialized the patient proxy, so listing appointments is now N+1.',
            );
        }

        $this->assertSame(3, $linked, 'The three seeded appointments should all carry a patient.');
    }

    public function testTheIdGetterStillAnswersFromAnUnloadedProxy(): void
    {
        $userRepository = self::getContainer()->get(UserRepositoryInterface::class);
        $appointmentRepository = self::getContainer()->get(AppointmentRepositoryInterface::class);

        $patient = DomainTestHelper::createActivePatient(email: 'id-' . bin2hex(random_bytes(4)) . '@test.com');
        $userRepository->save($patient);

        $appointment = DomainTestHelper::createRequestedAppointment(patient: $patient);
        $appointmentRepository->save($appointment);

        $this->entityManager->clear();

        $reloaded = $appointmentRepository->findById($appointment->getId());

        $this->assertNotNull($reloaded);
        $this->assertTrue($patient->getId()->equals($reloaded->getPatientId()));
        $this->assertTrue($this->entityManager->getUnitOfWork()->isUninitializedObject($reloaded->getPatient()));
    }

    /**
     * No PHP-side cascade is configured anywhere, on purpose: the ON DELETE rules in the
     * migrations own this. That only holds while the database keeps enforcing them.
     */
    public function testDeletingAUserAppliesTheDatabaseDeleteRules(): void
    {
        $userRepository = self::getContainer()->get(UserRepositoryInterface::class);
        $appointmentRepository = self::getContainer()->get(AppointmentRepositoryInterface::class);
        $scheduleRepository = self::getContainer()->get(TherapistScheduleRepositoryInterface::class);
        $exceptionRepository = self::getContainer()->get(ScheduleExceptionRepositoryInterface::class);
        $invitationRepository = self::getContainer()->get(InvitationTokenRepositoryInterface::class);
        $resetTokenRepository = self::getContainer()->get(PasswordResetTokenRepositoryInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $therapist = DomainTestHelper::createTherapist(email: 'cascade-t-' . $suffix . '@test.com');
        $patient = DomainTestHelper::createActivePatient(email: 'cascade-p-' . $suffix . '@test.com');
        $userRepository->save($therapist);
        $userRepository->save($patient);

        $appointment = DomainTestHelper::createRequestedAppointment(patient: $patient);
        $appointmentRepository->save($appointment);

        $schedule = DomainTestHelper::createScheduleBlock(therapist: $therapist);
        $scheduleRepository->save($schedule);

        $exception = DomainTestHelper::createScheduleException(therapist: $therapist);
        $exceptionRepository->save($exception);

        $invitation = DomainTestHelper::createValidInvitation(
            token: 'cascade-' . $suffix,
            email: 'invited-' . $suffix . '@test.com',
            invitedBy: $therapist,
        );
        $invitationRepository->save($invitation);

        $resetToken = DomainTestHelper::createValidPasswordResetToken(
            token: 'cascade-reset-' . $suffix,
            user: $patient,
        );
        $resetTokenRepository->save($resetToken);

        // Drop the seeded graph first. A delete endpoint loads the user and nothing else,
        // and Doctrine validates associations of whatever else happens to be managed.
        $this->entityManager->clear();

        $userRepository->delete($therapist);
        $userRepository->delete($patient);
        $this->entityManager->clear();

        // SET NULL: the appointment survives, orphaned. A therapist still needs the booking.
        $survivingAppointment = $appointmentRepository->findById($appointment->getId());
        $this->assertNotNull($survivingAppointment, 'Deleting a patient must not delete their appointments.');
        $this->assertNull($survivingAppointment->getPatientId());

        // CASCADE: everything that only exists to serve the user goes with them.
        $this->assertNull($scheduleRepository->findById($schedule->getId()));
        $this->assertNull($exceptionRepository->findById($exception->getId()));
        $this->assertNull($invitationRepository->findById($invitation->getId()));
        $this->assertNull($resetTokenRepository->findById($resetToken->getId()));
    }
}
