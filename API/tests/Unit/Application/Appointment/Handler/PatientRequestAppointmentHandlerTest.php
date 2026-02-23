<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\PatientRequestAppointmentInputDTO;
use App\Application\Appointment\DTO\Output\AppointmentOutputDTO;
use App\Application\Appointment\Handler\PatientRequestAppointmentHandler;
use App\Application\Appointment\Service\AppointmentRequestServiceInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\IncompleteProfileException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Address;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PatientRequestAppointmentHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private AppointmentRequestServiceInterface&MockObject $appointmentRequestService;
    private PatientRequestAppointmentHandler $handler;

    private const PATIENT_ID = '019525f3-5be1-7190-a6e1-aaa000000099';

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->appointmentRequestService = $this->createMock(AppointmentRequestServiceInterface::class);

        $this->handler = new PatientRequestAppointmentHandler(
            $this->userRepository,
            $this->appointmentRequestService,
        );
    }

    private function createPatientWithCompleteProfile(): User
    {
        return User::reconstitute(
            id: UserId::fromString(self::PATIENT_ID),
            email: Email::fromString('patient@example.com'),
            fullName: 'Test Patient',
            role: UserRole::PATIENT,
            password: 'hashed',
            phone: Phone::fromString('+1234567890'),
            address: Address::create('123 Test St', 'New York', 'US'),
            isActive: true,
            createdAt: new \DateTimeImmutable(),
            activatedAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    private function createPatientWithoutPhone(): User
    {
        return User::reconstitute(
            id: UserId::fromString(self::PATIENT_ID),
            email: Email::fromString('patient@example.com'),
            fullName: 'Test Patient',
            role: UserRole::PATIENT,
            password: 'hashed',
            phone: null,
            address: Address::create('123 Test St', 'New York', 'US'),
            isActive: true,
            createdAt: new \DateTimeImmutable(),
            activatedAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    private function createPatientWithoutAddress(): User
    {
        return User::reconstitute(
            id: UserId::fromString(self::PATIENT_ID),
            email: Email::fromString('patient@example.com'),
            fullName: 'Test Patient',
            role: UserRole::PATIENT,
            password: 'hashed',
            phone: Phone::fromString('+1234567890'),
            address: null,
            isActive: true,
            createdAt: new \DateTimeImmutable(),
            activatedAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    private function createInputDTO(?string $lockToken = null): PatientRequestAppointmentInputDTO
    {
        return new PatientRequestAppointmentInputDTO(
            patientId: self::PATIENT_ID,
            slotStartTime: '2025-06-02 09:00:00',
            modality: 'ONLINE',
            lockToken: $lockToken,
        );
    }

    public function testHandleSuccessWithCompleteProfile(): void
    {
        $patient = $this->createPatientWithCompleteProfile();

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($patient);

        $expectedOutput = new AppointmentOutputDTO(
            id: '019525f3-5be1-7190-a6e1-aaa000000001',
            startTime: '2025-06-02T09:00:00+00:00',
            endTime: '2025-06-02T09:50:00+00:00',
            modality: 'ONLINE',
            status: 'REQUESTED',
            fullName: 'Test Patient',
            email: 'patient@example.com',
            phone: '+1234567890',
            city: 'New York',
            country: 'US',
            patientId: self::PATIENT_ID,
            paymentVerified: false,
            createdAt: '2025-06-02T09:00:00+00:00',
            updatedAt: '2025-06-02T09:00:00+00:00',
        );

        $this->appointmentRequestService
            ->expects($this->once())
            ->method('requestAppointment')
            ->with(
                '2025-06-02 09:00:00',
                'ONLINE',
                'Test Patient',
                '+1234567890',
                'patient@example.com',
                'New York',
                'US',
                null,
                self::PATIENT_ID,
            )
            ->willReturn($expectedOutput);

        $result = $this->handler->__invoke($this->createInputDTO());

        $this->assertSame(self::PATIENT_ID, $result->patientId);
        $this->assertSame('REQUESTED', $result->status);
        $this->assertSame('Test Patient', $result->fullName);
    }

    public function testHandleSuccessPassesLockToken(): void
    {
        $patient = $this->createPatientWithCompleteProfile();

        $this->userRepository
            ->method('findById')
            ->willReturn($patient);

        $expectedOutput = new AppointmentOutputDTO(
            id: '019525f3-5be1-7190-a6e1-aaa000000001',
            startTime: '2025-06-02T09:00:00+00:00',
            endTime: '2025-06-02T09:50:00+00:00',
            modality: 'ONLINE',
            status: 'REQUESTED',
            fullName: 'Test Patient',
            email: 'patient@example.com',
            phone: '+1234567890',
            city: 'New York',
            country: 'US',
            patientId: self::PATIENT_ID,
            paymentVerified: false,
            createdAt: '2025-06-02T09:00:00+00:00',
            updatedAt: '2025-06-02T09:00:00+00:00',
        );

        $this->appointmentRequestService
            ->expects($this->once())
            ->method('requestAppointment')
            ->with(
                '2025-06-02 09:00:00',
                'ONLINE',
                'Test Patient',
                '+1234567890',
                'patient@example.com',
                'New York',
                'US',
                'my-lock-token',
                self::PATIENT_ID,
            )
            ->willReturn($expectedOutput);

        $result = $this->handler->__invoke($this->createInputDTO('my-lock-token'));

        $this->assertSame(self::PATIENT_ID, $result->patientId);
    }

    public function testHandlePatientNotFoundThrowsException(): void
    {
        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->appointmentRequestService
            ->expects($this->never())
            ->method('requestAppointment');

        $this->expectException(UserNotFoundException::class);
        $this->handler->__invoke($this->createInputDTO());
    }

    public function testHandleMissingPhoneThrowsIncompleteProfileException(): void
    {
        $patient = $this->createPatientWithoutPhone();

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($patient);

        $this->appointmentRequestService
            ->expects($this->never())
            ->method('requestAppointment');

        $this->expectException(IncompleteProfileException::class);
        $this->handler->__invoke($this->createInputDTO());
    }

    public function testHandleMissingAddressThrowsIncompleteProfileException(): void
    {
        $patient = $this->createPatientWithoutAddress();

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($patient);

        $this->appointmentRequestService
            ->expects($this->never())
            ->method('requestAppointment');

        $this->expectException(IncompleteProfileException::class);
        $this->handler->__invoke($this->createInputDTO());
    }
}
