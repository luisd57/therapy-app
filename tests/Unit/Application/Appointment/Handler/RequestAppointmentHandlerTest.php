<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\RequestAppointmentInputDTO;
use App\Application\Appointment\Handler\RequestAppointmentHandler;
use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Exception\InvalidLockTokenException;
use App\Domain\Appointment\Exception\SlotNotAvailableException;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\Repository\SlotLockRepositoryInterface;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Service\AppointmentEmailSenderInterface;
use App\Domain\Appointment\Service\AvailabilityComputerInterface;
use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\Appointment\ValueObject\SlotLockId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RequestAppointmentHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private SlotLockRepositoryInterface&MockObject $slotLockRepository;
    private TherapistScheduleRepositoryInterface&MockObject $scheduleRepository;
    private ScheduleExceptionRepositoryInterface&MockObject $exceptionRepository;
    private AvailabilityComputerInterface&MockObject $availabilityComputer;
    private AppointmentEmailSenderInterface&MockObject $emailSender;
    private RequestAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->slotLockRepository = $this->createMock(SlotLockRepositoryInterface::class);
        $this->scheduleRepository = $this->createMock(TherapistScheduleRepositoryInterface::class);
        $this->exceptionRepository = $this->createMock(ScheduleExceptionRepositoryInterface::class);
        $this->availabilityComputer = $this->createMock(AvailabilityComputerInterface::class);
        $this->emailSender = $this->createMock(AppointmentEmailSenderInterface::class);

        $this->handler = new RequestAppointmentHandler(
            $this->userRepository,
            $this->appointmentRepository,
            $this->slotLockRepository,
            $this->scheduleRepository,
            $this->exceptionRepository,
            $this->availabilityComputer,
            $this->emailSender,
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

    private function createInputDTO(?string $lockToken = null): RequestAppointmentInputDTO
    {
        return new RequestAppointmentInputDTO(
            slotStartTime: '2025-06-02 09:00:00',
            modality: 'ONLINE',
            fullName: 'Jane Doe',
            phone: '+1234567890',
            email: 'jane@example.com',
            city: 'Berlin',
            country: 'Germany',
            lockToken: $lockToken,
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
            ->method('findBlockingByDateRange')
            ->willReturn(new ArrayCollection());

        $matchingSlot = TimeSlot::create(new \DateTimeImmutable('2025-06-02 09:00:00'), 50);

        $this->availabilityComputer
            ->method('computeAvailableSlots')
            ->willReturn(new ArrayCollection([$matchingSlot]));
    }

    public function testHandleSuccessWithoutLockToken(): void
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

        $input = $this->createInputDTO();

        $result = $this->handler->__invoke($input);

        $this->assertSame('ONLINE', $result->modality);
        $this->assertSame('REQUESTED', $result->status);
        $this->assertSame('Jane Doe', $result->fullName);
        $this->assertSame('jane@example.com', $result->email);
        $this->assertSame('Berlin', $result->city);
        $this->assertSame('Germany', $result->country);
    }

    public function testHandleSuccessWithValidLockToken(): void
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

        $input = $this->createInputDTO('valid-lock-token');

        $result = $this->handler->__invoke($input);

        $this->assertSame('REQUESTED', $result->status);
        $this->assertSame('Jane Doe', $result->fullName);
    }

    public function testHandleInvalidLockTokenThrowsException(): void
    {
        $this->slotLockRepository
            ->method('findByLockToken')
            ->with('invalid-token')
            ->willReturn(null);

        $this->appointmentRepository
            ->expects($this->never())
            ->method('save');

        $input = $this->createInputDTO('invalid-token');

        $this->expectException(InvalidLockTokenException::class);
        $this->handler->__invoke($input);
    }

    public function testHandleSlotNotAvailableThrowsException(): void
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
            ->method('findBlockingByDateRange')
            ->willReturn(new ArrayCollection());

        // Return empty slots so the requested slot is not available
        $this->availabilityComputer
            ->method('computeAvailableSlots')
            ->willReturn(new ArrayCollection());

        $this->appointmentRepository
            ->expects($this->never())
            ->method('save');

        $input = $this->createInputDTO();

        $this->expectException(SlotNotAvailableException::class);
        $this->handler->__invoke($input);
    }
}
