<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Id\AppointmentId;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use DateTimeImmutable;

/**
 * Persists the appointment the therapist appointment controller tests act on.
 */
trait SeedsAppointment
{
    protected function createTestAppointment(string $status = 'REQUESTED'): Appointment
    {
        $appointment = Appointment::request(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day 10:00'), 50),
            modality: AppointmentModality::ONLINE,
            fullName: 'Test Patient',
            email: Email::fromString('patient@test.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: 'USA',
            now: new DateTimeImmutable(),
        );

        if ($status === 'CONFIRMED') {
            $appointment->confirm(new DateTimeImmutable());
        }

        $repo = self::getContainer()->get(AppointmentRepositoryInterface::class);
        $repo->save($appointment);

        return $appointment;
    }
}
