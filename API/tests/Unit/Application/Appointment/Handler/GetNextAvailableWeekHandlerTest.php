<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\GetNextAvailableWeekInputDTO;
use App\Application\Appointment\Handler\GetNextAvailableWeekHandler;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\Repository\SlotLockRepositoryInterface;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Service\AvailabilityComputerInterface;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetNextAvailableWeekHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private TherapistScheduleRepositoryInterface&MockObject $scheduleRepository;
    private ScheduleExceptionRepositoryInterface&MockObject $exceptionRepository;
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private SlotLockRepositoryInterface&MockObject $slotLockRepository;
    private AvailabilityComputerInterface&MockObject $availabilityComputer;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->scheduleRepository = $this->createMock(TherapistScheduleRepositoryInterface::class);
        $this->exceptionRepository = $this->createMock(ScheduleExceptionRepositoryInterface::class);
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->slotLockRepository = $this->createMock(SlotLockRepositoryInterface::class);
        $this->availabilityComputer = $this->createMock(AvailabilityComputerInterface::class);
    }

    private function createHandler(int $maxLookaheadWeeks = 3): GetNextAvailableWeekHandler
    {
        return new GetNextAvailableWeekHandler(
            $this->userRepository,
            $this->scheduleRepository,
            $this->exceptionRepository,
            $this->appointmentRepository,
            $this->slotLockRepository,
            $this->availabilityComputer,
            50,
            $maxLookaheadWeeks,
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

    private function setUpDefaultMocks(): void
    {
        $this->userRepository
            ->method('findSingleTherapist')
            ->willReturn($this->createTherapist());

        $this->scheduleRepository
            ->method('findActiveByTherapist')
            ->willReturn(new ArrayCollection());

        $this->exceptionRepository
            ->method('findByTherapistAndDateRange')
            ->willReturn(new ArrayCollection());

        $this->appointmentRepository
            ->method('findBlockingByDateRange')
            ->willReturn(new ArrayCollection());

        $this->slotLockRepository
            ->method('findActiveByDateRange')
            ->willReturn(new ArrayCollection());
    }

    public function testReturnsFirstWeekWithSlots(): void
    {
        $this->setUpDefaultMocks();

        $slot = TimeSlot::create(new \DateTimeImmutable('tomorrow 09:00:00'), 50);

        // First call (week 0) returns empty, second call (week 1) returns a slot
        $this->availabilityComputer
            ->method('computeAvailableSlots')
            ->willReturnOnConsecutiveCalls(
                new ArrayCollection(),
                new ArrayCollection([$slot]),
            );

        $handler = $this->createHandler(3);
        $result = $handler->__invoke(new GetNextAvailableWeekInputDTO());

        $this->assertTrue($result->found);
        $this->assertNotNull($result->weekStart);
        $this->assertNotNull($result->weekEnd);
        $this->assertSame(1, $result->totalSlots);
        $this->assertNotEmpty($result->slotsByDate);
    }

    public function testReturnsFoundFalseWhenNoSlotsInLookahead(): void
    {
        $this->setUpDefaultMocks();

        $this->availabilityComputer
            ->method('computeAvailableSlots')
            ->willReturn(new ArrayCollection());

        $handler = $this->createHandler(3);
        $result = $handler->__invoke(new GetNextAvailableWeekInputDTO());

        $this->assertFalse($result->found);
        $this->assertNull($result->weekStart);
        $this->assertNull($result->weekEnd);
        $this->assertSame(0, $result->totalSlots);
        $this->assertEmpty($result->slotsByDate);
    }

    public function testPassesModalityFilterToComputer(): void
    {
        $this->setUpDefaultMocks();

        $slot = TimeSlot::create(new \DateTimeImmutable('tomorrow 09:00:00'), 50);

        $this->availabilityComputer
            ->expects($this->atLeastOnce())
            ->method('computeAvailableSlots')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->callback(fn ($modality) => $modality !== null && $modality->value === 'ONLINE'),
            )
            ->willReturn(new ArrayCollection([$slot]));

        $handler = $this->createHandler(3);
        $result = $handler->__invoke(new GetNextAvailableWeekInputDTO(modality: 'ONLINE'));

        $this->assertTrue($result->found);
        $this->assertSame('ONLINE', $result->modality);
    }

    public function testSchedulesLoadedOnlyOnce(): void
    {
        $this->userRepository
            ->method('findSingleTherapist')
            ->willReturn($this->createTherapist());

        $this->scheduleRepository
            ->expects($this->once())
            ->method('findActiveByTherapist')
            ->willReturn(new ArrayCollection());

        // These should be called once per week (3 times for 3-week lookahead)
        $this->exceptionRepository
            ->expects($this->exactly(3))
            ->method('findByTherapistAndDateRange')
            ->willReturn(new ArrayCollection());

        $this->appointmentRepository
            ->expects($this->exactly(3))
            ->method('findBlockingByDateRange')
            ->willReturn(new ArrayCollection());

        $this->slotLockRepository
            ->expects($this->exactly(3))
            ->method('findActiveByDateRange')
            ->willReturn(new ArrayCollection());

        $this->availabilityComputer
            ->method('computeAvailableSlots')
            ->willReturn(new ArrayCollection());

        $handler = $this->createHandler(3);
        $handler->__invoke(new GetNextAvailableWeekInputDTO());
    }

    public function testReturnsCurrentWeekIfItHasSlots(): void
    {
        $this->setUpDefaultMocks();

        $slot = TimeSlot::create(new \DateTimeImmutable('tomorrow 09:00:00'), 50);

        $this->availabilityComputer
            ->expects($this->once())
            ->method('computeAvailableSlots')
            ->willReturn(new ArrayCollection([$slot]));

        $handler = $this->createHandler(3);
        $result = $handler->__invoke(new GetNextAvailableWeekInputDTO());

        $this->assertTrue($result->found);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $this->assertSame($today, $result->weekStart);
    }

    public function testOutputDtoToArray(): void
    {
        $this->setUpDefaultMocks();

        $slot = TimeSlot::create(new \DateTimeImmutable('tomorrow 09:00:00'), 50);

        $this->availabilityComputer
            ->method('computeAvailableSlots')
            ->willReturn(new ArrayCollection([$slot]));

        $handler = $this->createHandler(3);
        $result = $handler->__invoke(new GetNextAvailableWeekInputDTO());

        $array = $result->toArray();

        $this->assertArrayHasKey('found', $array);
        $this->assertArrayHasKey('week_start', $array);
        $this->assertArrayHasKey('week_end', $array);
        $this->assertArrayHasKey('modality', $array);
        $this->assertArrayHasKey('slots_by_date', $array);
        $this->assertArrayHasKey('total_slots', $array);
        $this->assertTrue($array['found']);
    }
}
