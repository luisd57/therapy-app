<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Appointment\Entity;

use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Enum\WeekDay;
use App\Tests\Helper\DomainTestHelper;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TherapistScheduleTest extends TestCase
{
    public function testScheduleBlockHoldsTheTherapistItBelongsTo(): void
    {
        $therapist = DomainTestHelper::createTherapist();

        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: $therapist,
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '17:00',
            now: new DateTimeImmutable(),
        );

        $this->assertSame($therapist, $schedule->getTherapist());
        $this->assertTrue($therapist->getId()->equals($schedule->getTherapistId()));
    }

    // --- create() ---

    public function testCreateSetsAllPropertiesCorrectly(): void
    {
        $id = ScheduleId::generate();
        $therapist = DomainTestHelper::createTherapist();
        $now = new DateTimeImmutable('2026-03-01T10:00:00+00:00');

        $schedule = TherapistSchedule::create(
            id: $id,
            therapist: $therapist,
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '17:00',
            now: $now,
            supportsOnline: true,
            supportsInPerson: false,
        );

        $this->assertTrue($id->equals($schedule->getId()));
        $this->assertTrue($therapist->getId()->equals($schedule->getTherapistId()));
        $this->assertSame(WeekDay::MONDAY, $schedule->getDayOfWeek());
        $this->assertSame('09:00', $schedule->getStartTime());
        $this->assertSame('17:00', $schedule->getEndTime());
        $this->assertTrue($schedule->isSupportsOnline());
        $this->assertFalse($schedule->isSupportsInPerson());
        $this->assertTrue($schedule->isActive());
        $this->assertSame($now, $schedule->getCreatedAt());
        $this->assertSame($now, $schedule->getUpdatedAt());
    }

    public function testCreateDefaultsToActiveAndBothModalities(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::TUESDAY,
            startTime: '10:00',
            endTime: '14:00',
            now: new DateTimeImmutable(),
        );

        $this->assertTrue($schedule->isActive());
        $this->assertTrue($schedule->isSupportsOnline());
        $this->assertTrue($schedule->isSupportsInPerson());
    }

    /**
     * Each start sorts before its end, so only the rule named in the message can refuse it.
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function refusedWallClockRules(): iterable
    {
        yield 'malformed start' => ['0900', '17:00', 'Invalid time format: 0900. Expected HH:MM.'];
        yield 'malformed end' => ['09:00', '1700', 'Invalid time format: 1700. Expected HH:MM.'];
        yield 'junk before the end' => ['09:00', 'x17:00', 'Invalid time format: x17:00. Expected HH:MM.'];
        yield 'junk after the end' => ['09:00', '17:00x', 'Invalid time format: 17:00x. Expected HH:MM.'];
        yield 'hour past 23' => ['09:00', '24:00', 'Invalid time value: 24:00.'];
        yield 'minute past 59' => ['09:00', '17:60', 'Invalid time value: 17:60.'];
    }

    #[DataProvider('refusedWallClockRules')]
    public function testCreateRefusesABadWallClockRule(string $startTime, string $endTime, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::MONDAY,
            startTime: $startTime,
            endTime: $endTime,
            now: new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
        );
    }

    public function testCreateAcceptsABlockFrom2300To2359(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::MONDAY,
            startTime: '23:00',
            endTime: '23:59',
            now: new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
        );

        $this->assertSame('23:00', $schedule->getStartTime());
        $this->assertSame('23:59', $schedule->getEndTime());
    }

    public function testCreateWithStartTimeAfterEndTimeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Start time must be before end time.');

        TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::MONDAY,
            startTime: '17:00',
            endTime: '09:00',
            now: new DateTimeImmutable(),
        );
    }

    public function testCreateWithEqualStartAndEndTimeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Start time must be before end time.');

        TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '09:00',
            now: new DateTimeImmutable(),
        );
    }

    // --- update() ---

    public function testUpdateChangesFields(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '17:00',
            now: new DateTimeImmutable(),
        );

        $schedule->update(
            dayOfWeek: WeekDay::WEDNESDAY,
            startTime: '10:00',
            endTime: '15:00',
            supportsOnline: false,
            supportsInPerson: true,
            now: new DateTimeImmutable(),
        );

        $this->assertSame(WeekDay::WEDNESDAY, $schedule->getDayOfWeek());
        $this->assertSame('10:00', $schedule->getStartTime());
        $this->assertSame('15:00', $schedule->getEndTime());
        $this->assertFalse($schedule->isSupportsOnline());
        $this->assertTrue($schedule->isSupportsInPerson());
    }

    public function testUpdateWithInvalidTimeThrowsException(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '17:00',
            now: new DateTimeImmutable(),
        );

        $this->expectException(\InvalidArgumentException::class);

        $schedule->update(
            dayOfWeek: WeekDay::MONDAY,
            startTime: '17:00',
            endTime: '09:00',
            supportsOnline: true,
            supportsInPerson: true,
            now: new DateTimeImmutable(),
        );
    }

    #[DataProvider('refusedWallClockRules')]
    public function testUpdateRefusesABadWallClockRule(string $startTime, string $endTime, string $message): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '17:00',
            now: new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $schedule->update(
            dayOfWeek: WeekDay::MONDAY,
            startTime: $startTime,
            endTime: $endTime,
            supportsOnline: true,
            supportsInPerson: true,
            now: new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
        );
    }

    public function testUpdateWithEqualStartAndEndTimeThrowsException(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '17:00',
            now: new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Start time must be before end time.');

        $schedule->update(
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '09:00',
            supportsOnline: true,
            supportsInPerson: true,
            now: new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
        );
    }

    // --- deactivate / activate ---

    public function testDeactivateTogglesIsActive(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '17:00',
            now: new DateTimeImmutable(),
        );

        $this->assertTrue($schedule->isActive());

        $schedule->deactivate(new DateTimeImmutable());

        $this->assertFalse($schedule->isActive());
    }

    public function testActivateTogglesIsActive(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '17:00',
            now: new DateTimeImmutable(),
        );

        $schedule->deactivate(new DateTimeImmutable());
        $this->assertFalse($schedule->isActive());

        $schedule->activate(new DateTimeImmutable());

        $this->assertTrue($schedule->isActive());
    }

    // --- supportsModality ---

    public function testSupportsModalityOnlineWhenEnabled(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::FRIDAY,
            startTime: '09:00',
            endTime: '12:00',
            now: new DateTimeImmutable(),
            supportsOnline: true,
            supportsInPerson: false,
        );

        $this->assertTrue($schedule->supportsModality(AppointmentModality::ONLINE));
    }

    public function testSupportsModalityOnlineWhenDisabled(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::FRIDAY,
            startTime: '09:00',
            endTime: '12:00',
            now: new DateTimeImmutable(),
            supportsOnline: false,
            supportsInPerson: true,
        );

        $this->assertFalse($schedule->supportsModality(AppointmentModality::ONLINE));
    }

    public function testSupportsModalityInPersonWhenEnabled(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::FRIDAY,
            startTime: '09:00',
            endTime: '12:00',
            now: new DateTimeImmutable(),
            supportsOnline: false,
            supportsInPerson: true,
        );

        $this->assertTrue($schedule->supportsModality(AppointmentModality::IN_PERSON));
    }

    public function testSupportsModalityInPersonWhenDisabled(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: DomainTestHelper::createTherapist(),
            dayOfWeek: WeekDay::FRIDAY,
            startTime: '09:00',
            endTime: '12:00',
            now: new DateTimeImmutable(),
            supportsOnline: true,
            supportsInPerson: false,
        );

        $this->assertFalse($schedule->supportsModality(AppointmentModality::IN_PERSON));
    }

    // --- reconstitute ---

    public function testReconstituteRestoresAllProperties(): void
    {
        $id = ScheduleId::generate();
        $therapist = DomainTestHelper::createTherapist();
        $createdAt = new DateTimeImmutable('-1 day');
        $updatedAt = new DateTimeImmutable();

        $schedule = TherapistSchedule::reconstitute(
            id: $id,
            therapist: $therapist,
            dayOfWeek: WeekDay::THURSDAY,
            startTime: '08:00',
            endTime: '16:00',
            supportsOnline: false,
            supportsInPerson: true,
            isActive: false,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        $this->assertTrue($id->equals($schedule->getId()));
        $this->assertTrue($therapist->getId()->equals($schedule->getTherapistId()));
        $this->assertSame(WeekDay::THURSDAY, $schedule->getDayOfWeek());
        $this->assertSame('08:00', $schedule->getStartTime());
        $this->assertSame('16:00', $schedule->getEndTime());
        $this->assertFalse($schedule->isSupportsOnline());
        $this->assertTrue($schedule->isSupportsInPerson());
        $this->assertFalse($schedule->isActive());
        $this->assertSame($createdAt, $schedule->getCreatedAt());
        $this->assertSame($updatedAt, $schedule->getUpdatedAt());
    }
}
