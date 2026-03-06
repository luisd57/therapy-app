<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Appointment\Service;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Service\AvailabilityComputer;
use App\Domain\Appointment\Service\AvailabilityContext;
use App\Domain\Appointment\Id\AppointmentId;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Enum\AppointmentStatus;
use App\Domain\Appointment\Id\ExceptionId;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Id\SlotLockId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\Appointment\Enum\WeekDay;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\Id\UserId;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

final class AvailabilityComputerTest extends TestCase
{
    private AvailabilityComputer $computer;

    protected function setUp(): void
    {
        $this->computer = new AvailabilityComputer();
    }

    /**
     * Helper: Find a future date that falls on the given WeekDay.
     * Returns a date at least 30 days in the future to avoid "past slot" filtering.
     */
    private function findFutureDateForWeekDay(WeekDay $weekDay): DateTimeImmutable
    {
        // Start 60 days in the future to be safe
        $date = new DateTimeImmutable('+60 days');
        $currentDayNumber = (int) $date->format('N');
        $targetDayNumber = $weekDay->value;

        $daysToAdd = ($targetDayNumber - $currentDayNumber + 7) % 7;
        if ($daysToAdd === 0) {
            return $date;
        }

        return $date->modify("+{$daysToAdd} days");
    }

    private function createSchedule(
        WeekDay $dayOfWeek,
        string $startTime,
        string $endTime,
        bool $supportsOnline = true,
        bool $supportsInPerson = true,
        bool $isActive = true,
    ): TherapistSchedule {
        $now = new DateTimeImmutable();

        return TherapistSchedule::reconstitute(
            id: ScheduleId::generate(),
            therapistId: UserId::generate(),
            dayOfWeek: $dayOfWeek,
            startTime: $startTime,
            endTime: $endTime,
            supportsOnline: $supportsOnline,
            supportsInPerson: $supportsInPerson,
            isActive: $isActive,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function createAppointment(
        DateTimeImmutable $startTime,
        int $durationMinutes,
        AppointmentStatus $status = AppointmentStatus::CONFIRMED,
    ): Appointment {
        $now = new DateTimeImmutable();

        return Appointment::reconstitute(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create($startTime, $durationMinutes),
            modality: AppointmentModality::ONLINE,
            status: $status,
            fullName: 'Test Patient',
            email: Email::fromString('patient@example.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'TestCity',
            country: 'TestCountry',
            patientId: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function createScheduleException(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): ScheduleException {
        return ScheduleException::reconstitute(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: $start,
            endDateTime: $end,
            reason: 'Blocked',
            isAllDay: false,
            createdAt: new DateTimeImmutable(),
        );
    }

    private function createActiveLock(
        DateTimeImmutable $startTime,
        int $durationMinutes,
    ): SlotLock {
        return SlotLock::reconstitute(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create($startTime, $durationMinutes),
            modality: AppointmentModality::ONLINE,
            lockToken: 'lock-token-' . bin2hex(random_bytes(4)),
            createdAt: new DateTimeImmutable('-5 minutes'),
            expiresAt: new DateTimeImmutable('+10 minutes'),
        );
    }

    private function createExpiredLock(
        DateTimeImmutable $startTime,
        int $durationMinutes,
    ): SlotLock {
        return SlotLock::reconstitute(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create($startTime, $durationMinutes),
            modality: AppointmentModality::ONLINE,
            lockToken: 'expired-lock-token',
            createdAt: new DateTimeImmutable('-2 hours'),
            expiresAt: new DateTimeImmutable('-1 hour'),
        );
    }

    private function createContext(
        array $schedules = [],
        array $exceptions = [],
        array $blockingAppointments = [],
        array $activeLocks = [],
    ): AvailabilityContext {
        return new AvailabilityContext(
            schedules: new ArrayCollection($schedules),
            exceptions: new ArrayCollection($exceptions),
            blockingAppointments: new ArrayCollection($blockingAppointments),
            activeLocks: new ArrayCollection($activeLocks),
        );
    }

    // --- Empty schedules ---

    public function testEmptySchedulesReturnsNoSlots(): void
    {
        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(),
            from: new DateTimeImmutable('+60 days'),
            to: new DateTimeImmutable('+67 days'),
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
        );

        $this->assertCount(0, $result);
    }

    // --- Basic slot generation ---

    public function testOneScheduleBlockGeneratesCorrectNumberOfSlots(): void
    {
        // 09:00-12:00 with 50-min slots should generate 3 slots:
        // 09:00-09:50, 09:50-10:40, 10:40-11:30
        // 11:30 + 50 = 12:20 > 12:00, so no 4th slot
        $targetDay = WeekDay::MONDAY;
        $date = $this->findFutureDateForWeekDay($targetDay);

        $schedule = $this->createSchedule($targetDay, '09:00', '12:00');

        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(schedules: [$schedule]),
            from: $date,
            to: $date,
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
        );

        $this->assertCount(3, $result);

        $dateStr = $date->format('Y-m-d');
        $slots = $result->toArray();

        $this->assertSame($dateStr . ' 09:00', $slots[0]->getStartTime()->format('Y-m-d H:i'));
        $this->assertSame($dateStr . ' 09:50', $slots[1]->getStartTime()->format('Y-m-d H:i'));
        $this->assertSame($dateStr . ' 10:40', $slots[2]->getStartTime()->format('Y-m-d H:i'));
    }

    public function testSlotsThatExactlyFitTheBlock(): void
    {
        // 09:00-10:00 with 30-min slots: 09:00-09:30, 09:30-10:00 = 2 slots
        $targetDay = WeekDay::TUESDAY;
        $date = $this->findFutureDateForWeekDay($targetDay);

        $schedule = $this->createSchedule($targetDay, '09:00', '10:00');

        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(schedules: [$schedule]),
            from: $date,
            to: $date,
            slotDurationMinutes: 30,
            now: new DateTimeImmutable(),
        );

        $this->assertCount(2, $result);
    }

    // --- Schedule exceptions block slots ---

    public function testScheduleExceptionsBlockSlots(): void
    {
        $targetDay = WeekDay::WEDNESDAY;
        $date = $this->findFutureDateForWeekDay($targetDay);
        $dateStr = $date->format('Y-m-d');

        $schedule = $this->createSchedule($targetDay, '09:00', '12:00');

        // Exception from 09:00 to 10:00 should block the first slot (09:00-09:50)
        $exception = $this->createScheduleException(
            new DateTimeImmutable($dateStr . ' 09:00'),
            new DateTimeImmutable($dateStr . ' 10:00'),
        );

        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(schedules: [$schedule], exceptions: [$exception]),
            from: $date,
            to: $date,
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
        );

        // Without exception: 3 slots (09:00, 09:50, 10:40)
        // Exception blocks 09:00-10:00, which overlaps 09:00-09:50 AND 09:50-10:40
        // So only 10:40-11:30 remains
        $this->assertCount(1, $result);
        $slots = $result->toArray();
        $this->assertSame($dateStr . ' 10:40', $slots[0]->getStartTime()->format('Y-m-d H:i'));
    }

    // --- Existing appointments remove slots ---

    public function testExistingBlockingAppointmentsRemoveSlots(): void
    {
        $targetDay = WeekDay::THURSDAY;
        $date = $this->findFutureDateForWeekDay($targetDay);
        $dateStr = $date->format('Y-m-d');

        $schedule = $this->createSchedule($targetDay, '09:00', '12:00');

        // Confirmed appointment at 09:00-09:50 should block that slot
        $appointment = $this->createAppointment(
            new DateTimeImmutable($dateStr . ' 09:00'),
            50,
            AppointmentStatus::CONFIRMED,
        );

        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(schedules: [$schedule], blockingAppointments: [$appointment]),
            from: $date,
            to: $date,
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
        );

        // 3 slots normally, 1 blocked by appointment = 2 remaining
        $this->assertCount(2, $result);
        $slots = $result->toArray();
        $this->assertSame($dateStr . ' 09:50', $slots[0]->getStartTime()->format('Y-m-d H:i'));
        $this->assertSame($dateStr . ' 10:40', $slots[1]->getStartTime()->format('Y-m-d H:i'));
    }

    public function testCancelledAppointmentDoesNotBlockSlot(): void
    {
        $targetDay = WeekDay::THURSDAY;
        $date = $this->findFutureDateForWeekDay($targetDay);
        $dateStr = $date->format('Y-m-d');

        $schedule = $this->createSchedule($targetDay, '09:00', '12:00');

        // Cancelled appointment should NOT block
        $appointment = $this->createAppointment(
            new DateTimeImmutable($dateStr . ' 09:00'),
            50,
            AppointmentStatus::CANCELLED,
        );

        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(schedules: [$schedule], blockingAppointments: [$appointment]),
            from: $date,
            to: $date,
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
        );

        // All 3 slots remain because cancelled appointments don't block
        $this->assertCount(3, $result);
    }

    // --- Active locks remove slots ---

    public function testActiveLocksRemoveSlots(): void
    {
        $targetDay = WeekDay::FRIDAY;
        $date = $this->findFutureDateForWeekDay($targetDay);
        $dateStr = $date->format('Y-m-d');

        $schedule = $this->createSchedule($targetDay, '09:00', '12:00');

        $lock = $this->createActiveLock(
            new DateTimeImmutable($dateStr . ' 09:50'),
            50,
        );

        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(schedules: [$schedule], activeLocks: [$lock]),
            from: $date,
            to: $date,
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
        );

        // 3 slots normally, 1 blocked by active lock = 2 remaining
        $this->assertCount(2, $result);
        $slots = $result->toArray();
        $this->assertSame($dateStr . ' 09:00', $slots[0]->getStartTime()->format('Y-m-d H:i'));
        $this->assertSame($dateStr . ' 10:40', $slots[1]->getStartTime()->format('Y-m-d H:i'));
    }

    public function testExpiredLocksDoNotBlockSlots(): void
    {
        $targetDay = WeekDay::FRIDAY;
        $date = $this->findFutureDateForWeekDay($targetDay);
        $dateStr = $date->format('Y-m-d');

        $schedule = $this->createSchedule($targetDay, '09:00', '12:00');

        $lock = $this->createExpiredLock(
            new DateTimeImmutable($dateStr . ' 09:50'),
            50,
        );

        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(schedules: [$schedule], activeLocks: [$lock]),
            from: $date,
            to: $date,
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
        );

        // All 3 slots remain because expired locks don't block
        $this->assertCount(3, $result);
    }

    // --- Past slots are filtered ---

    public function testPastSlotsAreFilteredOut(): void
    {
        // Use yesterday's date - all slots should be filtered as past
        $yesterday = new DateTimeImmutable('yesterday');
        $weekDay = WeekDay::fromDateTimeImmutable($yesterday);

        $schedule = $this->createSchedule($weekDay, '09:00', '12:00');

        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(schedules: [$schedule]),
            from: $yesterday,
            to: $yesterday,
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
        );

        $this->assertCount(0, $result);
    }

    // --- Modality filter ---

    public function testModalityFilterOnlyReturnsMatchingSlots(): void
    {
        $targetDay = WeekDay::SATURDAY;
        $date = $this->findFutureDateForWeekDay($targetDay);

        // This schedule only supports ONLINE
        $onlineSchedule = $this->createSchedule(
            $targetDay,
            '09:00',
            '10:30',
            supportsOnline: true,
            supportsInPerson: false,
        );

        // Filter for IN_PERSON should return no slots since schedule is online-only
        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(schedules: [$onlineSchedule]),
            from: $date,
            to: $date,
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
            modalityFilter: AppointmentModality::IN_PERSON,
        );

        $this->assertCount(0, $result);
    }

    public function testModalityFilterReturnsMatchingScheduleSlots(): void
    {
        $targetDay = WeekDay::SATURDAY;
        $date = $this->findFutureDateForWeekDay($targetDay);

        // This schedule only supports ONLINE
        $onlineSchedule = $this->createSchedule(
            $targetDay,
            '09:00',
            '10:30',
            supportsOnline: true,
            supportsInPerson: false,
        );

        // Filter for ONLINE should return slots
        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(schedules: [$onlineSchedule]),
            from: $date,
            to: $date,
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
            modalityFilter: AppointmentModality::ONLINE,
        );

        // 09:00-10:30, 50-min slots: 09:00-09:50 only (09:50+50=10:40 > 10:30)
        $this->assertCount(1, $result);
    }

    public function testNoModalityFilterReturnsSlotsFromAllModalities(): void
    {
        $targetDay = WeekDay::SATURDAY;
        $date = $this->findFutureDateForWeekDay($targetDay);

        $onlineOnlySchedule = $this->createSchedule(
            $targetDay,
            '09:00',
            '10:30',
            supportsOnline: true,
            supportsInPerson: false,
        );

        // Without filter, should return slots
        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(schedules: [$onlineOnlySchedule]),
            from: $date,
            to: $date,
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
            modalityFilter: null,
        );

        $this->assertCount(1, $result);
    }

    // --- Inactive schedules are skipped ---

    public function testInactiveSchedulesAreSkipped(): void
    {
        $targetDay = WeekDay::MONDAY;
        $date = $this->findFutureDateForWeekDay($targetDay);

        $inactiveSchedule = $this->createSchedule(
            $targetDay,
            '09:00',
            '12:00',
            isActive: false,
        );

        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(schedules: [$inactiveSchedule]),
            from: $date,
            to: $date,
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
        );

        $this->assertCount(0, $result);
    }

    // --- Multiple days ---

    public function testMultipleDaysWithDifferentScheduleBlocks(): void
    {
        $mondayDate = $this->findFutureDateForWeekDay(WeekDay::MONDAY);
        $tuesdayDate = $mondayDate->modify('+1 day');

        $mondaySchedule = $this->createSchedule(WeekDay::MONDAY, '09:00', '12:00');
        // Tuesday: 14:00-16:00 with 50-min slots: 14:00-14:50, 14:50-15:40 = 2 slots
        $tuesdaySchedule = $this->createSchedule(WeekDay::TUESDAY, '14:00', '16:00');

        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(schedules: [$mondaySchedule, $tuesdaySchedule]),
            from: $mondayDate,
            to: $tuesdayDate,
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
        );

        // Monday: 3 slots, Tuesday: 2 slots = 5 total
        $this->assertCount(5, $result);
    }

    public function testScheduleOnNonMatchingDayProducesNoSlots(): void
    {
        $mondayDate = $this->findFutureDateForWeekDay(WeekDay::MONDAY);

        // Schedule for Wednesday, but we only look at Monday
        $wednesdaySchedule = $this->createSchedule(WeekDay::WEDNESDAY, '09:00', '12:00');

        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(schedules: [$wednesdaySchedule]),
            from: $mondayDate,
            to: $mondayDate,
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
        );

        $this->assertCount(0, $result);
    }

    // --- Combined filters ---

    public function testCombinedExceptionAndAppointmentFiltering(): void
    {
        $targetDay = WeekDay::WEDNESDAY;
        $date = $this->findFutureDateForWeekDay($targetDay);
        $dateStr = $date->format('Y-m-d');

        // 09:00-12:00 generates 3 slots: 09:00, 09:50, 10:40
        $schedule = $this->createSchedule($targetDay, '09:00', '12:00');

        // Exception blocks 09:00-09:50 slot
        $exception = $this->createScheduleException(
            new DateTimeImmutable($dateStr . ' 08:30'),
            new DateTimeImmutable($dateStr . ' 09:30'),
        );

        // Appointment blocks 10:40-11:30 slot
        $appointment = $this->createAppointment(
            new DateTimeImmutable($dateStr . ' 10:40'),
            50,
            AppointmentStatus::CONFIRMED,
        );

        $result = $this->computer->computeAvailableSlots(
            context: $this->createContext(
                schedules: [$schedule],
                exceptions: [$exception],
                blockingAppointments: [$appointment],
            ),
            from: $date,
            to: $date,
            slotDurationMinutes: 50,
            now: new DateTimeImmutable(),
        );

        // Only 09:50-10:40 should remain
        $this->assertCount(1, $result);
        $slots = $result->toArray();
        $this->assertSame($dateStr . ' 09:50', $slots[0]->getStartTime()->format('Y-m-d H:i'));
    }
}
