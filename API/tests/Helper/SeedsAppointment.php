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
        // A test asking for a terminal status gets an error rather than a silently REQUESTED appointment.
        $appointment = match ($appointmentStatus) {
            AppointmentStatus::REQUESTED => DomainTestHelper::createRequestedAppointment(
                fullName: 'Test Patient',
                email: 'patient@test.com',
            ),
            AppointmentStatus::CONFIRMED => DomainTestHelper::createConfirmedAppointment(
                fullName: 'Test Patient',
                email: 'patient@test.com',
            ),
            AppointmentStatus::COMPLETED, AppointmentStatus::CANCELLED => throw new \LogicException(
                'Seed REQUESTED or CONFIRMED, then transition it to reach a terminal status.',
            ),
        };

        $repo = self::getContainer()->get(AppointmentRepositoryInterface::class);
        $repo->save($appointment);

        return $appointment;
    }
}
