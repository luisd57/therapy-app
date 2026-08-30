<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Appointment\Service;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Service\AvailabilityComputer;
use App\Domain\Appointment\Service\AvailabilityContext;
use App\Domain\Appointment\Service\SlotGenerationRules;
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
use App\Tests\Helper\DomainTestHelper;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * Expected values here are absolute UTC instants written out by hand, never
 * re-derived by formatting the same objects the computer builds. That is the
 * point: the suite runs at UTC+14, so a test that formats its own fixtures
 * would shift along with the code and could never disagree with it.
 *
 * 2026-06-01 is a Monday. America/Caracas is UTC-4 with no daylight saving,
 * so a Caracas wall-clock time is always UTC minus four hours.
 */
final class AvailabilityComputerTest extends TestCase
{
    private AvailabilityComputer $computer;

    protected function setUp(): void
    {
        $this->computer = new AvailabilityComputer();
    }

    private static function utc(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('UTC'));
    }

    private static function practiceTimeZone(): DateTimeZone
    {
        return new DateTimeZone('America/Caracas');
    }

    private static function rules(int $durationMinutes = 90, ?int $startIncrementMinutes = null): SlotGenerationRules
    {
        return SlotGenerationRules::create(
            durationMinutes: $durationMinutes,
            practiceTimeZone: self::practiceTimeZone(),
            startIncrementMinutes: $startIncrementMinutes,
        );
    }

    /**
     * @param ArrayCollection<int, TimeSlot> $slots
     *
     * @return list<string>
     */
    private static function startsAsUtc(ArrayCollection $slots): array
    {
        return array_values($slots->map(
            fn (TimeSlot $timeSlot): string => $timeSlot->getStartTime()
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z'),
        )->toArray());
    }

    private function createSchedule(
        WeekDay $dayOfWeek,
        string $startTime,
        string $endTime,
        bool $supportsOnline = true,
        bool $supportsInPerson = true,
        bool $isActive = true,
    ): TherapistSchedule {
        return TherapistSchedule::reconstitute(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: $dayOfWeek,
            startTime: $startTime,
            endTime: $endTime,
            supportsOnline: $supportsOnline,
            supportsInPerson: $supportsInPerson,
            isActive: $isActive,
            createdAt: self::utc('2026-01-01 00:00:00'),
            updatedAt: self::utc('2026-01-01 00:00:00'),
        );
    }

    private function createAppointment(
        DateTimeImmutable $startTime,
        int $durationMinutes,
        AppointmentStatus $status = AppointmentStatus::CONFIRMED,
    ): Appointment {
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
            patient: null,
            createdAt: self::utc('2026-01-01 00:00:00'),
            updatedAt: self::utc('2026-01-01 00:00:00'),
        );
    }

    private function createScheduleException(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): ScheduleException {
        return ScheduleException::reconstitute(
            id: ExceptionId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            startDateTime: $start,
            endDateTime: $end,
            reason: 'Blocked',
            isAllDay: false,
            createdAt: self::utc('2026-01-01 00:00:00'),
        );
    }

    private function createLock(
        DateTimeImmutable $startTime,
        int $durationMinutes,
        DateTimeImmutable $expiresAt,
    ): SlotLock {
        return SlotLock::reconstitute(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create($startTime, $durationMinutes),
            modality: AppointmentModality::ONLINE,
            lockToken: 'lock-token-' . bin2hex(random_bytes(4)),
            createdAt: self::utc('2026-05-01 00:00:00'),
            expiresAt: $expiresAt,
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

    // --- Practice-zone materialisation ---

    public function testScheduleBlocksAreReadInThePracticeZoneNotTheProcessZone(): void
    {
        // Monday 08:00-12:00 in Caracas is 12:00-16:00 UTC. At 90 minutes the
        // third start (11:00 local) would end at 12:30, past the block.
        $result = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(
                schedules: [$this->createSchedule(WeekDay::MONDAY, '08:00', '12:00')],
            ),
            slotGenerationRules: self::rules(durationMinutes: 90, startIncrementMinutes: 90),
            from: self::utc('2026-06-01 00:00:00'),
            to: self::utc('2026-06-02 00:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
        );

        $this->assertSame(
            ['2026-06-01T12:00:00Z', '2026-06-01T13:30:00Z'],
            self::startsAsUtc($result),
        );
    }

    /**
     * The headline regression. 2026-06-02T00:00:00Z is Monday 20:00 in Caracas,
     * so the Monday block must apply. Reading the weekday off the UTC instant
     * would say Tuesday and return nothing.
     */
    public function testWeekdayIsTakenFromThePracticeZoneNotUtc(): void
    {
        $result = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(
                schedules: [$this->createSchedule(WeekDay::MONDAY, '20:00', '23:00')],
            ),
            slotGenerationRules: self::rules(durationMinutes: 90, startIncrementMinutes: 90),
            from: self::utc('2026-06-02 00:00:00'),
            to: self::utc('2026-06-02 06:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
        );

        $this->assertSame(
            ['2026-06-02T00:00:00Z', '2026-06-02T01:30:00Z'],
            self::startsAsUtc($result),
        );
    }

    // --- Start increments ---

    public function testStartIncrementOffersOverlappingCandidateStarts(): void
    {
        // 08:00-12:00 Caracas, 90-minute sessions starting every 30 minutes.
        // Last viable start is 10:30 local (ends exactly at 12:00).
        $result = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(
                schedules: [$this->createSchedule(WeekDay::MONDAY, '08:00', '12:00')],
            ),
            slotGenerationRules: self::rules(durationMinutes: 90, startIncrementMinutes: 30),
            from: self::utc('2026-06-01 00:00:00'),
            to: self::utc('2026-06-02 00:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
        );

        $this->assertSame(
            [
                '2026-06-01T12:00:00Z',
                '2026-06-01T12:30:00Z',
                '2026-06-01T13:00:00Z',
                '2026-06-01T13:30:00Z',
                '2026-06-01T14:00:00Z',
                '2026-06-01T14:30:00Z',
            ],
            self::startsAsUtc($result),
        );
    }

    public function testIncrementDefaultsToTheDurationProducingABackToBackGrid(): void
    {
        $result = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(
                schedules: [$this->createSchedule(WeekDay::MONDAY, '08:00', '12:00')],
            ),
            slotGenerationRules: self::rules(durationMinutes: 90),
            from: self::utc('2026-06-01 00:00:00'),
            to: self::utc('2026-06-02 00:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
        );

        $this->assertSame(
            ['2026-06-01T12:00:00Z', '2026-06-01T13:30:00Z'],
            self::startsAsUtc($result),
        );
    }

    public function testSlotMustFitEntirelyWithinTheBlock(): void
    {
        $result = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(
                schedules: [$this->createSchedule(WeekDay::MONDAY, '08:00', '09:00')],
            ),
            slotGenerationRules: self::rules(durationMinutes: 90, startIncrementMinutes: 30),
            from: self::utc('2026-06-01 00:00:00'),
            to: self::utc('2026-06-02 00:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
        );

        $this->assertCount(0, $result);
    }

    /**
     * Her real Tuesday window. She said only two consultations fit, and that is
     * capacity, not offered starts: six candidates, at most two non-overlapping.
     */
    public function testTuesdayMorningWindowOffersSixCandidateStarts(): void
    {
        $result = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(
                schedules: [$this->createSchedule(WeekDay::TUESDAY, '06:30', '10:30')],
            ),
            slotGenerationRules: self::rules(durationMinutes: 90, startIncrementMinutes: 30),
            from: self::utc('2026-06-02 00:00:00'),
            to: self::utc('2026-06-03 00:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
        );

        // 06:30 Caracas is 10:30 UTC; last start 09:00 local ends exactly at 10:30.
        $this->assertSame(
            [
                '2026-06-02T10:30:00Z',
                '2026-06-02T11:00:00Z',
                '2026-06-02T11:30:00Z',
                '2026-06-02T12:00:00Z',
                '2026-06-02T12:30:00Z',
                '2026-06-02T13:00:00Z',
            ],
            self::startsAsUtc($result),
        );
    }

    // --- Overlap suppression ---

    public function testConfirmedAppointmentSuppressesEveryOverlappingCandidateStart(): void
    {
        // A 12:00-13:30 UTC booking (08:00 local) rules out every start whose
        // 90-minute span intersects it: 10:30 through 13:00 UTC.
        $result = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(
                schedules: [$this->createSchedule(WeekDay::MONDAY, '08:00', '13:00')],
                blockingAppointments: [
                    $this->createAppointment(self::utc('2026-06-01 12:00:00'), 90),
                ],
            ),
            slotGenerationRules: self::rules(durationMinutes: 90, startIncrementMinutes: 30),
            from: self::utc('2026-06-01 00:00:00'),
            to: self::utc('2026-06-02 00:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
        );

        // Block is 12:00-17:00 UTC; last viable start 15:30. 12:00-13:00 suppressed.
        $this->assertSame(
            [
                '2026-06-01T13:30:00Z',
                '2026-06-01T14:00:00Z',
                '2026-06-01T14:30:00Z',
                '2026-06-01T15:00:00Z',
                '2026-06-01T15:30:00Z',
            ],
            self::startsAsUtc($result),
        );
    }

    public function testCancelledAppointmentDoesNotSuppressSlots(): void
    {
        $result = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(
                schedules: [$this->createSchedule(WeekDay::MONDAY, '08:00', '11:00')],
                blockingAppointments: [
                    $this->createAppointment(
                        self::utc('2026-06-01 12:00:00'),
                        90,
                        AppointmentStatus::CANCELLED,
                    ),
                ],
            ),
            slotGenerationRules: self::rules(durationMinutes: 90, startIncrementMinutes: 90),
            from: self::utc('2026-06-01 00:00:00'),
            to: self::utc('2026-06-02 00:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
        );

        $this->assertSame(['2026-06-01T12:00:00Z', '2026-06-01T13:30:00Z'], self::startsAsUtc($result));
    }

    public function testActiveLockSuppressesOverlappingSlotsAndExpiredOneDoesNot(): void
    {
        $now = self::utc('2026-05-01 00:00:00');
        $schedules = [$this->createSchedule(WeekDay::MONDAY, '08:00', '11:00')];

        $withActiveLock = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(
                schedules: $schedules,
                activeLocks: [
                    $this->createLock(
                        self::utc('2026-06-01 12:00:00'),
                        90,
                        expiresAt: self::utc('2026-05-01 00:10:00'),
                    ),
                ],
            ),
            slotGenerationRules: self::rules(durationMinutes: 90, startIncrementMinutes: 90),
            from: self::utc('2026-06-01 00:00:00'),
            to: self::utc('2026-06-02 00:00:00'),
            now: $now,
        );

        $this->assertSame(['2026-06-01T13:30:00Z'], self::startsAsUtc($withActiveLock));

        $withExpiredLock = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(
                schedules: $schedules,
                activeLocks: [
                    $this->createLock(
                        self::utc('2026-06-01 12:00:00'),
                        90,
                        expiresAt: self::utc('2026-04-30 23:50:00'),
                    ),
                ],
            ),
            slotGenerationRules: self::rules(durationMinutes: 90, startIncrementMinutes: 90),
            from: self::utc('2026-06-01 00:00:00'),
            to: self::utc('2026-06-02 00:00:00'),
            now: $now,
        );

        $this->assertSame(
            ['2026-06-01T12:00:00Z', '2026-06-01T13:30:00Z'],
            self::startsAsUtc($withExpiredLock),
        );
    }

    /**
     * An all-day exception is a practice-local day, which in UTC runs 04:00 to
     * 04:00. It must blank the Monday it covers and leave Tuesday alone.
     */
    public function testAllDayExceptionBlocksThePracticeLocalDayOnly(): void
    {
        $result = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(
                schedules: [
                    $this->createSchedule(WeekDay::MONDAY, '08:00', '11:00'),
                    $this->createSchedule(WeekDay::TUESDAY, '08:00', '11:00'),
                ],
                exceptions: [
                    $this->createScheduleException(
                        self::utc('2026-06-01 04:00:00'),
                        self::utc('2026-06-02 04:00:00'),
                    ),
                ],
            ),
            slotGenerationRules: self::rules(durationMinutes: 90, startIncrementMinutes: 90),
            from: self::utc('2026-06-01 00:00:00'),
            to: self::utc('2026-06-03 00:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
        );

        $this->assertSame(
            ['2026-06-02T12:00:00Z', '2026-06-02T13:30:00Z'],
            self::startsAsUtc($result),
        );
    }

    // --- Window clipping and past filtering ---

    public function testSlotsStartingBeforeTheRequestedWindowAreExcluded(): void
    {
        $result = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(
                schedules: [$this->createSchedule(WeekDay::MONDAY, '08:00', '13:00')],
            ),
            slotGenerationRules: self::rules(durationMinutes: 90, startIncrementMinutes: 90),
            from: self::utc('2026-06-01 13:00:00'),
            to: self::utc('2026-06-02 00:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
        );

        // 12:00Z start is before the window; 13:30Z and 15:00Z survive.
        $this->assertSame(
            ['2026-06-01T13:30:00Z', '2026-06-01T15:00:00Z'],
            self::startsAsUtc($result),
        );
    }

    public function testPastSlotsAreFilteredRegardlessOfTheZoneNowIsExpressedIn(): void
    {
        $schedules = [$this->createSchedule(WeekDay::MONDAY, '08:00', '13:00')];

        $nowInUtc = self::utc('2026-06-01 13:00:00');
        $sameInstantInCaracas = new DateTimeImmutable('2026-06-01 09:00:00', self::practiceTimeZone());

        $arguments = [
            'availabilityContext' => $this->createContext(schedules: $schedules),
            'slotGenerationRules' => self::rules(durationMinutes: 90, startIncrementMinutes: 90),
            'from' => self::utc('2026-06-01 00:00:00'),
            'to' => self::utc('2026-06-02 00:00:00'),
        ];

        $fromUtc = $this->computer->computeAvailableSlots(...[...$arguments, 'now' => $nowInUtc]);
        $fromCaracas = $this->computer->computeAvailableSlots(...[...$arguments, 'now' => $sameInstantInCaracas]);

        $this->assertSame(['2026-06-01T13:30:00Z', '2026-06-01T15:00:00Z'], self::startsAsUtc($fromUtc));
        $this->assertSame(self::startsAsUtc($fromUtc), self::startsAsUtc($fromCaracas));
    }

    // --- Filtering that is independent of timezone ---

    public function testEmptySchedulesReturnsNoSlots(): void
    {
        $result = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(),
            slotGenerationRules: self::rules(),
            from: self::utc('2026-06-01 00:00:00'),
            to: self::utc('2026-06-08 00:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
        );

        $this->assertCount(0, $result);
    }

    public function testInactiveSchedulesAreSkipped(): void
    {
        $result = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(
                schedules: [$this->createSchedule(WeekDay::MONDAY, '08:00', '12:00', isActive: false)],
            ),
            slotGenerationRules: self::rules(),
            from: self::utc('2026-06-01 00:00:00'),
            to: self::utc('2026-06-02 00:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
        );

        $this->assertCount(0, $result);
    }

    public function testModalityFilterExcludesBlocksThatDoNotSupportIt(): void
    {
        $context = $this->createContext(
            schedules: [
                $this->createSchedule(
                    WeekDay::MONDAY,
                    '08:00',
                    '12:00',
                    supportsOnline: true,
                    supportsInPerson: false,
                ),
            ],
        );

        $inPerson = $this->computer->computeAvailableSlots(
            availabilityContext: $context,
            slotGenerationRules: self::rules(),
            from: self::utc('2026-06-01 00:00:00'),
            to: self::utc('2026-06-02 00:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
            modalityFilter: AppointmentModality::IN_PERSON,
        );

        $online = $this->computer->computeAvailableSlots(
            availabilityContext: $context,
            slotGenerationRules: self::rules(),
            from: self::utc('2026-06-01 00:00:00'),
            to: self::utc('2026-06-02 00:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
            modalityFilter: AppointmentModality::ONLINE,
        );

        $this->assertCount(0, $inPerson);
        $this->assertSame(['2026-06-01T12:00:00Z', '2026-06-01T13:30:00Z'], self::startsAsUtc($online));
    }

    public function testMultipleBlocksOnTheSameDayAreBothHonoured(): void
    {
        // Her Wednesday-to-Sunday shape: a morning block, lunch, an afternoon block.
        $result = $this->computer->computeAvailableSlots(
            availabilityContext: $this->createContext(
                schedules: [
                    $this->createSchedule(WeekDay::MONDAY, '08:00', '12:00'),
                    $this->createSchedule(WeekDay::MONDAY, '13:30', '19:30'),
                ],
            ),
            slotGenerationRules: self::rules(durationMinutes: 90, startIncrementMinutes: 90),
            from: self::utc('2026-06-01 00:00:00'),
            to: self::utc('2026-06-02 00:00:00'),
            now: self::utc('2026-05-01 00:00:00'),
        );

        $this->assertSame(
            [
                // 08:00 and 09:30 local
                '2026-06-01T12:00:00Z',
                '2026-06-01T13:30:00Z',
                // 13:30, 15:00, 16:30 and 18:00 local - the last ends at 19:30
                '2026-06-01T17:30:00Z',
                '2026-06-01T19:00:00Z',
                '2026-06-01T20:30:00Z',
                '2026-06-01T22:00:00Z',
            ],
            self::startsAsUtc($result),
        );
    }
}
