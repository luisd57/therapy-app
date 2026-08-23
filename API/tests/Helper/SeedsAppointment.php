<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Enum\AppointmentStatus;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;

/**
 * Persists the appointment the therapist appointment controller tests act on.
 */
trait SeedsAppointment
{
    protected function createTestAppointment(AppointmentStatus $appointmentStatus = AppointmentStatus::REQUESTED): Appointment
    {
        // No arm for the terminal statuses: a test asking for one gets an
        // UnhandledMatchError rather than a silently REQUESTED appointment.
        $appointment = match ($appointmentStatus) {
            AppointmentStatus::REQUESTED => DomainTestHelper::createRequestedAppointment(
                fullName: 'Test Patient',
                email: 'patient@test.com',
            ),
            AppointmentStatus::CONFIRMED => DomainTestHelper::createConfirmedAppointment(
                fullName: 'Test Patient',
                email: 'patient@test.com',
            ),
        };

        $repo = self::getContainer()->get(AppointmentRepositoryInterface::class);
        $repo->save($appointment);

        return $appointment;
    }
}
