<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Appointment\Entity;

use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Enum\WeekDay;
use App\Domain\User\Id\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TherapistScheduleTest extends TestCase
{
    // --- create() ---

    public function testCreateSetsAllPropertiesCorrectly(): void
    {
        $id = ScheduleId::generate();
        $therapistId = UserId::generate();

        $schedule = TherapistSchedule::create(
            id: $id,
            therapistId: $therapistId,
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '17:00',
            now: new DateTimeImmutable(),
            supportsOnline: true,
            supportsInPerson: false,
        );

        $this->assertTrue($id->equals($schedule->getId()));
        $this->assertTrue($therapistId->equals($schedule->getTherapistId()));
        $this->assertSame(WeekDay::MONDAY, $schedule->getDayOfWeek());
        $this->assertSame('09:00', $schedule->getStartTime());
        $this->assertSame('17:00', $schedule->getEndTime());
        $this->assertTrue($schedule->isSupportsOnline());
        $this->assertFalse($schedule->isSupportsInPerson());
        $this->assertTrue($schedule->isActive());
        $this->assertNotNull($schedule->getCreatedAt());
        $this->assertNotNull($schedule->getUpdatedAt());
    }

    public function testCreateDefaultsToActiveAndBothModalities(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapistId: UserId::generate(),
            dayOfWeek: WeekDay::TUESDAY,
            startTime: '10:00',
            endTime: '14:00',
            now: new DateTimeImmutable(),
        );

        $this->assertTrue($schedule->isActive());
        $this->assertTrue($schedule->isSupportsOnline());
        $this->assertTrue($schedule->isSupportsInPerson());
    }

    public function testCreateWithInvalidTimeFormatThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapistId: UserId::generate(),
            dayOfWeek: WeekDay::MONDAY,
            startTime: '9:00',
            endTime: '17:00',
            now: new DateTimeImmutable(),
        );
    }

    public function testCreateWithInvalidTimeValueThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapistId: UserId::generate(),
            dayOfWeek: WeekDay::MONDAY,
            startTime: '25:00',
            endTime: '17:00',
            now: new DateTimeImmutable(),
        );
    }

    public function testCreateWithStartTimeAfterEndTimeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Start time must be before end time.');

        TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapistId: UserId::generate(),
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
            therapistId: UserId::generate(),
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
            therapistId: UserId::generate(),
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
            therapistId: UserId::generate(),
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

    // --- deactivate / activate ---

    public function testDeactivateTogglesIsActive(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapistId: UserId::generate(),
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
            therapistId: UserId::generate(),
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
            therapistId: UserId::generate(),
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
            therapistId: UserId::generate(),
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
            therapistId: UserId::generate(),
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
            therapistId: UserId::generate(),
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
        $therapistId = UserId::generate();
        $createdAt = new DateTimeImmutable('-1 day');
        $updatedAt = new DateTimeImmutable();

        $schedule = TherapistSchedule::reconstitute(
            id: $id,
            therapistId: $therapistId,
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
        $this->assertTrue($therapistId->equals($schedule->getTherapistId()));
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
