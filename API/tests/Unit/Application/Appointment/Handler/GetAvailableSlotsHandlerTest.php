<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\GetAvailableSlotsInputDTO;
use App\Application\Appointment\Handler\GetAvailableSlotsHandler;
use App\Application\Appointment\Service\SlotGenerationRulesFactory;
use App\Infrastructure\Config\EnvPracticeTimezoneProvider;
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

        // Real collaborators: both are pure configuration with no I/O, so
        // mocking them would only restate their behaviour.
        $practiceTimezoneProvider = new EnvPracticeTimezoneProvider('America/Caracas');

        $this->handler = new GetAvailableSlotsHandler(
            $this->userRepository,
            $this->scheduleRepository,
            $this->exceptionRepository,
            $this->appointmentRepository,
            $this->availabilityComputer,
            new SlotGenerationRulesFactory(
                practiceTimezoneProvider: $practiceTimezoneProvider,
                appointmentDurationMinutes: 50,
                slotStartIncrementMinutes: 30,
            ),
            $practiceTimezoneProvider,
            $this->clock,
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

        // Slots are grouped by the practice-local day, so the fixture has to say
        // which zone 09:00 means rather than leaning on the process default.
        $slot = TimeSlot::create(
            new \DateTimeImmutable('2025-06-02 09:00:00', new \DateTimeZone('America/Caracas')),
            50,
        );

        $this->availabilityComputer
            ->method('computeAvailableSlots')
            ->willReturn(new ArrayCollection([$slot]));

        $input = new GetAvailableSlotsInputDTO(
            from: '2025-06-02T00:00:00-04:00',
            to: '2025-06-03T00:00:00-04:00',
        );

        $result = $this->handler->__invoke($input);

        // The window is echoed back normalised to UTC, so a client can see
        // exactly which instants the server understood.
        $this->assertSame('2025-06-02T04:00:00+00:00', $result->from);
        $this->assertSame('2025-06-03T04:00:00+00:00', $result->to);
        $this->assertNull($result->modality);
        $this->assertSame('America/Caracas', $result->practiceTimezone);
        $this->assertCount(1, $result->slots);
        $this->assertSame('2025-06-02T13:00:00+00:00', $result->slots->first()->startTime);
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
            from: '2025-06-02T00:00:00-04:00',
            to: '2025-06-03T00:00:00-04:00',
            modality: 'ONLINE',
        );

        $result = $this->handler->__invoke($input);

        $this->assertSame('ONLINE', $result->modality);
        $this->assertCount(0, $result->slots);
        $this->assertSame('America/Caracas', $result->practiceTimezone);
    }
}
