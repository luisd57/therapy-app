<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Appointment\Entity;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Exception\InvalidStatusTransitionException;
use App\Domain\Appointment\ValueObject\AppointmentId;
use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\Appointment\ValueObject\AppointmentStatus;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\ValueObject\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AppointmentTest extends TestCase
{
    private function createRequestedAppointment(?UserId $patientId = null): Appointment
    {
        return Appointment::request(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            fullName: 'John Doe',
            email: Email::fromString('john@example.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: 'USA',
            patientId: $patientId,
        );
    }

    // --- request() factory ---

    public function testRequestSetsAllPropertiesCorrectly(): void
    {
        $id = AppointmentId::generate();
        $timeSlot = TimeSlot::create(new DateTimeImmutable('+1 day'), 50);
        $patientId = UserId::generate();

        $appointment = Appointment::request(
            id: $id,
            timeSlot: $timeSlot,
            modality: AppointmentModality::ONLINE,
            fullName: 'John Doe',
            email: Email::fromString('john@example.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: 'USA',
            patientId: $patientId,
        );

        $this->assertTrue($id->equals($appointment->getId()));
        $this->assertTrue($timeSlot->equals($appointment->getTimeSlot()));
        $this->assertSame(AppointmentModality::ONLINE, $appointment->getModality());
        $this->assertSame(AppointmentStatus::REQUESTED, $appointment->getStatus());
        $this->assertSame('John Doe', $appointment->getFullName());
        $this->assertSame('john@example.com', $appointment->getEmail()->getValue());
        $this->assertSame('+1234567890', $appointment->getPhone()->getValue());
        $this->assertSame('New York', $appointment->getCity());
        $this->assertSame('USA', $appointment->getCountry());
        $this->assertTrue($patientId->equals($appointment->getPatientId()));
        $this->assertNotNull($appointment->getCreatedAt());
        $this->assertNotNull($appointment->getUpdatedAt());
    }

    public function testRequestWithNullPatientId(): void
    {
        $appointment = $this->createRequestedAppointment();

        $this->assertNull($appointment->getPatientId());
    }

    public function testRequestWithEmptyFullNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Full name is required.');

        Appointment::request(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            fullName: '',
            email: Email::fromString('john@example.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: 'USA',
        );
    }

    public function testRequestWithWhitespaceOnlyFullNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Full name is required.');

        Appointment::request(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            fullName: '   ',
            email: Email::fromString('john@example.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: 'USA',
        );
    }

    public function testRequestWithEmptyCityThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('City is required.');

        Appointment::request(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            fullName: 'John Doe',
            email: Email::fromString('john@example.com'),
            phone: Phone::fromString('+1234567890'),
            city: '',
            country: 'USA',
        );
    }

    public function testRequestWithEmptyCountryThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Country is required.');

        Appointment::request(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            fullName: 'John Doe',
            email: Email::fromString('john@example.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: '',
        );
    }

    // --- Status transitions ---

    public function testConfirmFromRequested(): void
    {
        $appointment = $this->createRequestedAppointment();

        $appointment->confirm();

        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->getStatus());
    }

    public function testCompleteFromConfirmed(): void
    {
        $appointment = $this->createRequestedAppointment();
        $appointment->confirm();

        $appointment->complete();

        $this->assertSame(AppointmentStatus::COMPLETED, $appointment->getStatus());
    }

    public function testCancelFromRequested(): void
    {
        $appointment = $this->createRequestedAppointment();

        $appointment->cancel();

        $this->assertSame(AppointmentStatus::CANCELLED, $appointment->getStatus());
    }

    public function testCancelFromConfirmed(): void
    {
        $appointment = $this->createRequestedAppointment();
        $appointment->confirm();

        $appointment->cancel();

        $this->assertSame(AppointmentStatus::CANCELLED, $appointment->getStatus());
    }

    // --- Invalid transitions ---

    public function testCompleteFromRequestedThrowsInvalidStatusTransitionException(): void
    {
        $appointment = $this->createRequestedAppointment();

        $this->expectException(InvalidStatusTransitionException::class);
        $appointment->complete();
    }

    public function testConfirmFromCompletedThrowsInvalidStatusTransitionException(): void
    {
        $id = AppointmentId::generate();
        $appointment = Appointment::reconstitute(
            id: $id,
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            status: AppointmentStatus::COMPLETED,
            fullName: 'John Doe',
            email: Email::fromString('john@example.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: 'USA',
            patientId: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        );

        $this->expectException(InvalidStatusTransitionException::class);
        $appointment->confirm();
    }

    public function testCancelFromCompletedThrowsInvalidStatusTransitionException(): void
    {
        $appointment = Appointment::reconstitute(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            status: AppointmentStatus::COMPLETED,
            fullName: 'John Doe',
            email: Email::fromString('john@example.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: 'USA',
            patientId: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        );

        $this->expectException(InvalidStatusTransitionException::class);
        $appointment->cancel();
    }

    public function testConfirmFromCancelledThrowsInvalidStatusTransitionException(): void
    {
        $appointment = Appointment::reconstitute(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            status: AppointmentStatus::CANCELLED,
            fullName: 'John Doe',
            email: Email::fromString('john@example.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: 'USA',
            patientId: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        );

        $this->expectException(InvalidStatusTransitionException::class);
        $appointment->confirm();
    }

    // --- blocksSlot ---

    public function testBlocksSlotForRequestedStatus(): void
    {
        $appointment = $this->createRequestedAppointment();

        $this->assertTrue($appointment->blocksSlot());
    }

    public function testBlocksSlotForConfirmedStatus(): void
    {
        $appointment = $this->createRequestedAppointment();
        $appointment->confirm();

        $this->assertTrue($appointment->blocksSlot());
    }

    public function testDoesNotBlockSlotForCompletedStatus(): void
    {
        $appointment = $this->createRequestedAppointment();
        $appointment->confirm();
        $appointment->complete();

        $this->assertFalse($appointment->blocksSlot());
    }

    public function testDoesNotBlockSlotForCancelledStatus(): void
    {
        $appointment = $this->createRequestedAppointment();
        $appointment->cancel();

        $this->assertFalse($appointment->blocksSlot());
    }

    // --- book() factory ---

    public function testBookSetsConfirmedStatus(): void
    {
        $appointment = Appointment::book(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            fullName: 'John Doe',
            email: Email::fromString('john@example.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: 'USA',
        );

        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->getStatus());
    }

    public function testBookWithPatientId(): void
    {
        $patientId = UserId::generate();

        $appointment = Appointment::book(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::IN_PERSON,
            fullName: 'Jane Smith',
            email: Email::fromString('jane@example.com'),
            phone: Phone::fromString('+9876543210'),
            city: 'Los Angeles',
            country: 'USA',
            patientId: $patientId,
        );

        $this->assertTrue($patientId->equals($appointment->getPatientId()));
        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->getStatus());
    }

    public function testBookWithEmptyFullNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Full name is required.');

        Appointment::book(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            fullName: '',
            email: Email::fromString('john@example.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: 'USA',
        );
    }

    // --- paymentVerified ---

    public function testPaymentVerifiedDefaultsToFalse(): void
    {
        $appointment = $this->createRequestedAppointment();

        $this->assertFalse($appointment->isPaymentVerified());
    }

    public function testMarkPaymentVerified(): void
    {
        $appointment = $this->createRequestedAppointment();

        $appointment->markPaymentVerified();

        $this->assertTrue($appointment->isPaymentVerified());
    }

    public function testMarkPaymentUnverified(): void
    {
        $appointment = $this->createRequestedAppointment();
        $appointment->markPaymentVerified();

        $appointment->markPaymentUnverified();

        $this->assertFalse($appointment->isPaymentVerified());
    }

    public function testReconstituteWithPaymentVerified(): void
    {
        $appointment = Appointment::reconstitute(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            status: AppointmentStatus::CONFIRMED,
            fullName: 'John Doe',
            email: Email::fromString('john@example.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: 'USA',
            patientId: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
            paymentVerified: true,
        );

        $this->assertTrue($appointment->isPaymentVerified());
    }

    // --- reconstitute ---

    public function testReconstituteRestoresAllProperties(): void
    {
        $id = AppointmentId::generate();
        $timeSlot = TimeSlot::create(new DateTimeImmutable('2026-04-01 10:00'), 50);
        $email = Email::fromString('jane@example.com');
        $phone = Phone::fromString('+9876543210');
        $patientId = UserId::generate();
        $createdAt = new DateTimeImmutable('-1 day');
        $updatedAt = new DateTimeImmutable();

        $appointment = Appointment::reconstitute(
            id: $id,
            timeSlot: $timeSlot,
            modality: AppointmentModality::IN_PERSON,
            status: AppointmentStatus::CONFIRMED,
            fullName: 'Jane Smith',
            email: $email,
            phone: $phone,
            city: 'Los Angeles',
            country: 'USA',
            patientId: $patientId,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        $this->assertTrue($id->equals($appointment->getId()));
        $this->assertTrue($timeSlot->equals($appointment->getTimeSlot()));
        $this->assertSame(AppointmentModality::IN_PERSON, $appointment->getModality());
        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->getStatus());
        $this->assertSame('Jane Smith', $appointment->getFullName());
        $this->assertTrue($email->equals($appointment->getEmail()));
        $this->assertTrue($phone->equals($appointment->getPhone()));
        $this->assertSame('Los Angeles', $appointment->getCity());
        $this->assertSame('USA', $appointment->getCountry());
        $this->assertTrue($patientId->equals($appointment->getPatientId()));
        $this->assertSame($createdAt, $appointment->getCreatedAt());
        $this->assertSame($updatedAt, $appointment->getUpdatedAt());
    }
}
