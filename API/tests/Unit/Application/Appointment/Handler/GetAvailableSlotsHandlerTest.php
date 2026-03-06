<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\GetAvailableSlotsInputDTO;
use App\Application\Appointment\Handler\GetAvailableSlotsHandler;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Service\AvailabilityComputerInterface;
use Symfony\Component\Clock\ClockInterface;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\Id\UserId;
use App\Domain\User\Enum\UserRole;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetAvailableSlotsHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private TherapistScheduleRepositoryInterface&MockObject $scheduleRepository;
    private ScheduleExceptionRepositoryInterface&MockObject $exceptionRepository;
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private AvailabilityComputerInterface&MockObject $availabilityComputer;
    private ClockInterface&MockObject $clock;
    private GetAvailableSlotsHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->scheduleRepository = $this->createMock(TherapistScheduleRepositoryInterface::class);
        $this->exceptionRepository = $this->createMock(ScheduleExceptionRepositoryInterface::class);
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->availabilityComputer = $this->createMock(AvailabilityComputerInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable());

        $this->handler = new GetAvailableSlotsHandler(
            $this->userRepository,
            $this->scheduleRepository,
            $this->exceptionRepository,
            $this->appointmentRepository,
            $this->availabilityComputer,
            $this->clock,
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

    public function testHandleSuccessReturnsAvailableSlotsOutputDTO(): void
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

        $slot = TimeSlot::create(new \DateTimeImmutable('2025-06-02 09:00:00'), 50);

        $this->availabilityComputer
            ->method('computeAvailableSlots')
            ->willReturn(new ArrayCollection([$slot]));

        $input = new GetAvailableSlotsInputDTO(
            from: '2025-06-02',
            to: '2025-06-02',
        );

        $result = $this->handler->__invoke($input);

        $this->assertSame('2025-06-02', $result->from);
        $this->assertSame('2025-06-02', $result->to);
        $this->assertNull($result->modality);
        $this->assertSame(1, $result->totalSlots);
        $this->assertArrayHasKey('2025-06-02', $result->slotsByDate);
        $this->assertCount(1, $result->slotsByDate['2025-06-02']);
    }

    public function testHandleWithModalityFilter(): void
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

        $input = new GetAvailableSlotsInputDTO(
            from: '2025-06-02',
            to: '2025-06-02',
            modality: 'ONLINE',
        );

        $result = $this->handler->__invoke($input);

        $this->assertSame('ONLINE', $result->modality);
        $this->assertSame(0, $result->totalSlots);
        $this->assertEmpty($result->slotsByDate);
    }
}
