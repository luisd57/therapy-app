<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Service;

use App\Application\Appointment\Service\AppointmentRequestService;
use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Exception\InvalidLockTokenException;
use App\Domain\Appointment\Exception\SlotNotAvailableException;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\Repository\SlotLockRepositoryInterface;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Service\AppointmentEmailSenderInterface;
use App\Domain\Appointment\Service\AvailabilityComputerInterface;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Id\SlotLockId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\Id\UserId;
use App\Domain\User\Enum\UserRole;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AppointmentRequestServiceTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private SlotLockRepositoryInterface&MockObject $slotLockRepository;
    private TherapistScheduleRepositoryInterface&MockObject $scheduleRepository;
    private ScheduleExceptionRepositoryInterface&MockObject $exceptionRepository;
    private AvailabilityComputerInterface&MockObject $availabilityComputer;
    private AppointmentEmailSenderInterface&MockObject $emailSender;
    private ClockInterface&MockObject $clock;
    private LoggerInterface&MockObject $logger;
    private AppointmentRequestService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->slotLockRepository = $this->createMock(SlotLockRepositoryInterface::class);
        $this->scheduleRepository = $this->createMock(TherapistScheduleRepositoryInterface::class);
        $this->exceptionRepository = $this->createMock(ScheduleExceptionRepositoryInterface::class);
        $this->availabilityComputer = $this->createMock(AvailabilityComputerInterface::class);
        $this->emailSender = $this->createMock(AppointmentEmailSenderInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable());
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new AppointmentRequestService(
            $this->userRepository,
            $this->appointmentRepository,
            $this->slotLockRepository,
            $this->scheduleRepository,
            $this->exceptionRepository,
            $this->availabilityComputer,
            $this->emailSender,
            $this->clock,
            $this->logger,
            50,
        );
    }

    private function createTherapist(): User
    {
        return User::reconstitute(
            id: UserId::fromString('019525f3-5be1-7190-a6e1-aaa000000001'),
            email: Email::fromString('therapist@test.com'),
            fullName: 'Dr. Test',
            role: UserRole::THERAPIST,
            password: 'hashed',
            phone: null,
            address: null,
            isActive: true,
            createdAt: new \DateTimeImmutable(),
            activatedAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    private function stubAvailabilityCheck(): void
    {
        $therapist = $this->createTherapist();

        $this->userRepository
            ->method('findSingleTherapist')
            ->willReturn($therapist);

        $this->scheduleRepository
            ->method('findActiveByTherapist')
            ->willReturn(new ArrayCollection());

        $this->exceptionRepository
            ->method('findByTherapistAndDateRange')
            ->willReturn(new ArrayCollection());

        $this->appointmentRepository
            ->method('findConfirmedByDateRange')
            ->willReturn(new ArrayCollection());

        $matchingSlot = TimeSlot::create(new \DateTimeImmutable('2025-06-02 09:00:00'), 50);

        $this->availabilityComputer
            ->method('computeAvailableSlots')
            ->willReturn(new ArrayCollection([$matchingSlot]));
    }

    public function testRequestAppointmentSuccessWithoutLockToken(): void
    {
        $this->stubAvailabilityCheck();

        $this->appointmentRepository
            ->expects($this->once())
            ->method('save');

        $this->emailSender
            ->expects($this->once())
            ->method('sendRequestAcknowledgment');

        $this->emailSender
            ->expects($this->once())
            ->method('sendNewRequestAlertToTherapist');

        $result = $this->service->requestAppointment(
            slotStartTime: '2025-06-02 09:00:00',
            modality: 'ONLINE',
            fullName: 'Jane Doe',
            phone: '+1234567890',
            email: 'jane@example.com',
            city: 'Berlin',
            country: 'Germany',
        );

        $this->assertSame('ONLINE', $result->modality);
        $this->assertSame('REQUESTED', $result->status);
        $this->assertSame('Jane Doe', $result->fullName);
        $this->assertSame('jane@example.com', $result->email);
        $this->assertSame('Berlin', $result->city);
        $this->assertSame('Germany', $result->country);
        $this->assertNull($result->patientId);
    }

    public function testRequestAppointmentSuccessWithValidLockToken(): void
    {
        $timeSlot = TimeSlot::create(new \DateTimeImmutable('2025-06-02 09:00:00'), 50);

        $lock = SlotLock::reconstitute(
            id: SlotLockId::generate(),
            timeSlot: $timeSlot,
            modality: AppointmentModality::ONLINE,
            lockToken: 'valid-lock-token',
            createdAt: new \DateTimeImmutable(),
            expiresAt: new \DateTimeImmutable('+10 minutes'),
        );

        $this->slotLockRepository
            ->method('findByLockToken')
            ->with('valid-lock-token')
            ->willReturn($lock);

        $this->slotLockRepository
            ->expects($this->once())
            ->method('delete')
            ->with($lock);

        $this->stubAvailabilityCheck();

        $this->appointmentRepository
            ->expects($this->once())
            ->method('save');

        $this->emailSender
            ->expects($this->once())
            ->method('sendRequestAcknowledgment');

        $this->emailSender
            ->expects($this->once())
            ->method('sendNewRequestAlertToTherapist');

        $result = $this->service->requestAppointment(
            slotStartTime: '2025-06-02 09:00:00',
            modality: 'ONLINE',
            fullName: 'Jane Doe',
            phone: '+1234567890',
            email: 'jane@example.com',
            city: 'Berlin',
            country: 'Germany',
            lockToken: 'valid-lock-token',
        );

        $this->assertSame('REQUESTED', $result->status);
        $this->assertSame('Jane Doe', $result->fullName);
    }

    public function testRequestAppointmentInvalidLockTokenThrowsException(): void
    {
        $this->slotLockRepository
            ->method('findByLockToken')
            ->with('invalid-token')
            ->willReturn(null);

        $this->appointmentRepository
            ->expects($this->never())
            ->method('save');

        $this->expectException(InvalidLockTokenException::class);

        $this->service->requestAppointment(
            slotStartTime: '2025-06-02 09:00:00',
            modality: 'ONLINE',
            fullName: 'Jane Doe',
            phone: '+1234567890',
            email: 'jane@example.com',
            city: 'Berlin',
            country: 'Germany',
            lockToken: 'invalid-token',
        );
    }

    public function testRequestAppointmentSlotNotAvailableThrowsException(): void
    {
        $therapist = $this->createTherapist();

        $this->userRepository
            ->method('findSingleTherapist')
            ->willReturn($therapist);

        $this->scheduleRepository
            ->method('findActiveByTherapist')
            ->willReturn(new ArrayCollection());

        $this->exceptionRepository
            ->method('findByTherapistAndDateRange')
            ->willReturn(new ArrayCollection());

        $this->appointmentRepository
            ->method('findConfirmedByDateRange')
            ->willReturn(new ArrayCollection());

        $this->availabilityComputer
            ->method('computeAvailableSlots')
            ->willReturn(new ArrayCollection());

        $this->appointmentRepository
            ->expects($this->never())
            ->method('save');

        $this->expectException(SlotNotAvailableException::class);

        $this->service->requestAppointment(
            slotStartTime: '2025-06-02 09:00:00',
            modality: 'ONLINE',
            fullName: 'Jane Doe',
            phone: '+1234567890',
            email: 'jane@example.com',
            city: 'Berlin',
            country: 'Germany',
        );
    }

    public function testRequestAppointmentWithPatientIdFlowsThrough(): void
    {
        $this->stubAvailabilityCheck();

        $this->appointmentRepository
            ->expects($this->once())
            ->method('save');

        $this->emailSender
            ->expects($this->once())
            ->method('sendRequestAcknowledgment');

        $this->emailSender
            ->expects($this->once())
            ->method('sendNewRequestAlertToTherapist');

        $patientId = '019525f3-5be1-7190-a6e1-aaa000000099';

        $result = $this->service->requestAppointment(
            slotStartTime: '2025-06-02 09:00:00',
            modality: 'ONLINE',
            fullName: 'Jane Doe',
            phone: '+1234567890',
            email: 'jane@example.com',
            city: 'Berlin',
            country: 'Germany',
            patientId: $patientId,
        );

        $this->assertSame($patientId, $result->patientId);
        $this->assertSame('REQUESTED', $result->status);
    }

    public function testRequestAppointmentSendsEmailNotifications(): void
    {
        $this->stubAvailabilityCheck();

        $this->appointmentRepository
            ->expects($this->once())
            ->method('save');

        $this->emailSender
            ->expects($this->once())
            ->method('sendRequestAcknowledgment')
            ->with(
                $this->callback(fn (Email $email) => $email->getValue() === 'jane@example.com'),
                'Jane Doe',
                $this->isInstanceOf(\DateTimeImmutable::class),
                AppointmentModality::ONLINE,
            );

        $this->emailSender
            ->expects($this->once())
            ->method('sendNewRequestAlertToTherapist');

        $this->service->requestAppointment(
            slotStartTime: '2025-06-02 09:00:00',
            modality: 'ONLINE',
            fullName: 'Jane Doe',
            phone: '+1234567890',
            email: 'jane@example.com',
            city: 'Berlin',
            country: 'Germany',
        );
    }

    public function testRequestAppointmentSucceedsWhenEmailFails(): void
    {
        $this->stubAvailabilityCheck();

        $this->appointmentRepository
            ->expects($this->once())
            ->method('save');

        $this->emailSender
            ->method('sendRequestAcknowledgment')
            ->willThrowException(new \RuntimeException('SMTP connection refused'));

        $this->logger
            ->expects($this->once())
            ->method('error');

        $result = $this->service->requestAppointment(
            slotStartTime: '2025-06-02 09:00:00',
            modality: 'ONLINE',
            fullName: 'Jane Doe',
            phone: '+1234567890',
            email: 'jane@example.com',
            city: 'Berlin',
            country: 'Germany',
        );

        $this->assertSame('REQUESTED', $result->status);
        $this->assertSame('Jane Doe', $result->fullName);
    }
}
