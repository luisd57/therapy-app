<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\GetNextAvailableWeekInputDTO;
use App\Application\Appointment\Handler\GetNextAvailableWeekHandler;
use App\Application\Appointment\Service\SlotGenerationRulesFactory;
use App\Infrastructure\Config\EnvPracticeTimezoneProvider;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Enum\WeekDay;
use App\Domain\Appointment\Service\AvailabilityComputer;
use App\Domain\Appointment\Service\AvailabilityComputerInterface;
use App\Tests\Helper\DomainTestHelper;
use Symfony\Component\Clock\ClockInterface;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\Id\UserId;
use App\Domain\User\Enum\UserRole;
use App\Domain\Appointment\Enum\AppointmentModality;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use App\Tests\Helper\UsesUtcInstants;
use App\Tests\Helper\WireShapes;
use PHPUnit\Framework\TestCase;

final class GetNextAvailableWeekHandlerTest extends TestCase
{
    use UsesUtcInstants;

    private UserRepositoryInterface&MockObject $userRepository;
    private TherapistScheduleRepositoryInterface&MockObject $scheduleRepository;
    private ScheduleExceptionRepositoryInterface&MockObject $exceptionRepository;
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private AvailabilityComputerInterface&MockObject $availabilityComputer;
    private ClockInterface&MockObject $clock;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->scheduleRepository = $this->createMock(TherapistScheduleRepositoryInterface::class);
        $this->exceptionRepository = $this->createMock(ScheduleExceptionRepositoryInterface::class);
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->availabilityComputer = $this->createMock(AvailabilityComputerInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        // Fixed instant: the week start is derived in the practice zone, so a
        // real "now" would make the expected date depend on when the suite runs.
        // 12:00 UTC is 08:00 the same day in Caracas.
        $this->clock->method('now')->willReturn(
            new \DateTimeImmutable('2026-06-15 12:00:00', new \DateTimeZone('UTC')),
        );
    }

    private function createHandler(
        int $maxLookaheadWeeks = 3,
        ?AvailabilityComputerInterface $availabilityComputer = null,
    ): GetNextAvailableWeekHandler {
        $practiceTimezoneProvider = new EnvPracticeTimezoneProvider('America/Caracas');

        return new GetNextAvailableWeekHandler(
            $this->userRepository,
            $this->scheduleRepository,
            $this->exceptionRepository,
            $this->appointmentRepository,
            $availabilityComputer ?? $this->availabilityComputer,
            new SlotGenerationRulesFactory(
                practiceTimezoneProvider: $practiceTimezoneProvider,
                appointmentDurationMinutes: 50,
                slotStartIncrementMinutes: 30,
            ),
            $practiceTimezoneProvider,
            $this->clock,
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
            ->method('findConfirmedByDateRange')
            ->willReturn(new ArrayCollection());
    }

    public function testReturnsFirstWeekWithSlots(): void
    {
        $this->setUpDefaultMocks();

        $slot = TimeSlot::create(self::utc('2026-06-16 13:00:00'), 50);

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
        $this->assertCount(1, $result->slots);
        $this->assertSame('America/Caracas', $result->practiceTimezone);
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
        $this->assertCount(0, $result->slots);
    }

    public function testDoesNotSearchPastTheLookahead(): void
    {
        $lookaheadWeeks = 3;
        $therapist = $this->createTherapist();
        // Practice-local midnight on the frozen Monday, where the first week starts.
        $firstWeekStart = self::utc('2026-06-15 04:00:00');

        $this->userRepository->method('findSingleTherapist')->willReturn($therapist);
        $this->scheduleRepository->method('findActiveByTherapist')->willReturn(new ArrayCollection([
            DomainTestHelper::createScheduleBlock($therapist, WeekDay::MONDAY, '08:00', '18:00'),
        ]));
        $this->appointmentRepository->method('findConfirmedByDateRange')->willReturn(new ArrayCollection());
        // Ends exactly where the lookahead ends. Any later and it would close the next week too.
        $this->exceptionRepository->method('findByTherapistAndDateRange')->willReturn(new ArrayCollection([
            DomainTestHelper::createScheduleException(
                therapist: $therapist,
                startDateTime: $firstWeekStart,
                endDateTime: $firstWeekStart->modify("+{$lookaheadWeeks} weeks"),
            ),
        ]));

        // The week right after the lookahead is open, so the not-found below is the bound's doing.
        $resultWithOneMoreWeek = $this->createHandler($lookaheadWeeks + 1, new AvailabilityComputer())
            ->__invoke(new GetNextAvailableWeekInputDTO());
        $this->assertTrue($resultWithOneMoreWeek->found);
        $this->assertSame('2026-07-06T04:00:00+00:00', $resultWithOneMoreWeek->weekStart);

        $result = $this->createHandler($lookaheadWeeks, new AvailabilityComputer())
            ->__invoke(new GetNextAvailableWeekInputDTO());

        $this->assertFalse($result->found);
        $this->assertNull($result->weekStart);
        $this->assertCount(0, $result->slots);
    }

    public function testPassesModalityFilterToComputer(): void
    {
        $this->setUpDefaultMocks();

        $slot = TimeSlot::create(self::utc('2026-06-16 13:00:00'), 50);

        $this->availabilityComputer
            ->expects($this->atLeastOnce())
            ->method('computeAvailableSlots')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->callback(fn (?AppointmentModality $modality) => $modality === AppointmentModality::ONLINE),
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

        // Batch-loaded once for the full lookahead range
        $this->exceptionRepository
            ->expects($this->once())
            ->method('findByTherapistAndDateRange')
            ->willReturn(new ArrayCollection());

        $this->appointmentRepository
            ->expects($this->once())
            ->method('findConfirmedByDateRange')
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

        $slot = TimeSlot::create(self::utc('2026-06-16 13:00:00'), 50);

        $this->availabilityComputer
            ->expects($this->once())
            ->method('computeAvailableSlots')
            ->willReturn(new ArrayCollection([$slot]));

        $handler = $this->createHandler(3);
        $result = $handler->__invoke(new GetNextAvailableWeekInputDTO());

        $this->assertTrue($result->found);
        // Practice-local midnight on 2026-06-15 is 04:00 UTC.
        $this->assertSame('2026-06-15T04:00:00+00:00', $result->weekStart);
    }

    public function testOutputDtoToArray(): void
    {
        $this->setUpDefaultMocks();

        $slot = TimeSlot::create(self::utc('2026-06-16 13:00:00'), 50);

        $this->availabilityComputer
            ->method('computeAvailableSlots')
            ->willReturn(new ArrayCollection([$slot]));

        $handler = $this->createHandler(3);
        $result = $handler->__invoke(new GetNextAvailableWeekInputDTO());

        $array = $result->toArray();

        $this->assertEqualsCanonicalizing(WireShapes::NEXT_AVAILABLE_WEEK, array_keys($array), 'toArray keys');
        $this->assertTrue($array['found']);
    }
}
