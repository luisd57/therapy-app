<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Appointment\Entity;

use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Id\ExceptionId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Id\UserId;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class ScheduleExceptionTest extends TestCase
{
    private const PRACTICE_TIMEZONE = 'America/Caracas';

    private static function practiceTimeZone(): DateTimeZone
    {
        return new DateTimeZone(self::PRACTICE_TIMEZONE);
    }

    private static function assertInstantIs(string $expectedUtc, DateTimeImmutable $actual): void
    {
        self::assertSame(
            $expectedUtc,
            $actual->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP'),
        );
    }

    // --- create() ---

    public function testCreateSetsAllProperties(): void
    {
        $id = ExceptionId::generate();
        $therapistId = UserId::generate();
        $start = new DateTimeImmutable('2026-04-01 09:00');
        $end = new DateTimeImmutable('2026-04-01 12:00');

        $exception = ScheduleException::create(
            id: $id,
            therapistId: $therapistId,
            startDateTime: $start,
            endDateTime: $end,
            now: new DateTimeImmutable(),
            practiceTimeZone: self::practiceTimeZone(),
            reason: 'Personal day',
            isAllDay: false,
        );

        $this->assertTrue($id->equals($exception->getId()));
        $this->assertTrue($therapistId->equals($exception->getTherapistId()));
        $this->assertSame($start, $exception->getStartDateTime());
        $this->assertSame($end, $exception->getEndDateTime());
        $this->assertSame('Personal day', $exception->getReason());
        $this->assertFalse($exception->isAllDay());
        $this->assertNotNull($exception->getCreatedAt());
    }

    public function testCreateWithEmptyReasonTrimsToEmpty(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: new DateTimeImmutable('2026-04-01 09:00'),
            endDateTime: new DateTimeImmutable('2026-04-01 12:00'),
            now: new DateTimeImmutable(),
            practiceTimeZone: self::practiceTimeZone(),
        );

        $this->assertSame('', $exception->getReason());
    }

    public function testCreateWithAllDayFlag(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: new DateTimeImmutable('2026-04-01T00:00:00-04:00'),
            endDateTime: new DateTimeImmutable('2026-04-02T00:00:00-04:00'),
            now: new DateTimeImmutable(),
            practiceTimeZone: self::practiceTimeZone(),
            reason: 'Holiday',
            isAllDay: true,
        );

        $this->assertTrue($exception->isAllDay());
    }

    public function testCreateWithEndBeforeStartThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('End date/time must be after start date/time.');

        ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: new DateTimeImmutable('2026-04-01 12:00'),
            endDateTime: new DateTimeImmutable('2026-04-01 09:00'),
            now: new DateTimeImmutable(),
            practiceTimeZone: self::practiceTimeZone(),
        );
    }

    public function testCreateWithEqualStartAndEndThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $time = new DateTimeImmutable('2026-04-01 12:00');
        ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: $time,
            endDateTime: $time,
            now: new DateTimeImmutable(),
            practiceTimeZone: self::practiceTimeZone(),
        );
    }

    // --- all-day snapping ---

    public function testAllDayExceptionFromAFarAwayZoneCoversThePracticeLocalDay(): void
    {
        // Submitted as a working day in Kiritimati (UTC+14). Read in Caracas the
        // range is 1 June 10:00 to 18:00, so the practice-local day is 1 June.
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: new DateTimeImmutable('2026-06-02T04:00:00+14:00'),
            endDateTime: new DateTimeImmutable('2026-06-02T12:00:00+14:00'),
            now: new DateTimeImmutable('2026-05-01T00:00:00+00:00'),
            practiceTimeZone: self::practiceTimeZone(),
            reason: 'Away',
            isAllDay: true,
        );

        // Caracas is UTC-4, so its midnight is 04:00 the same day in UTC.
        self::assertInstantIs('2026-06-01T04:00:00+00:00', $exception->getStartDateTime());
        self::assertInstantIs('2026-06-02T04:00:00+00:00', $exception->getEndDateTime());
    }

    public function testAllDayExceptionBlocksThePracticeEveningAndLeavesTheNextDayAlone(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: new DateTimeImmutable('2026-06-02T04:00:00+14:00'),
            endDateTime: new DateTimeImmutable('2026-06-02T12:00:00+14:00'),
            now: new DateTimeImmutable('2026-05-01T00:00:00+00:00'),
            practiceTimeZone: self::practiceTimeZone(),
            isAllDay: true,
        );

        // 19:00 in Caracas is already the next UTC day, which is what makes a
        // late-evening slot the one an unsnapped range misses.
        $evening = TimeSlot::fromStartEnd(
            new DateTimeImmutable('2026-06-01T23:00:00+00:00'),
            new DateTimeImmutable('2026-06-01T23:50:00+00:00'),
        );
        $eveningNextDay = TimeSlot::fromStartEnd(
            new DateTimeImmutable('2026-06-02T23:00:00+00:00'),
            new DateTimeImmutable('2026-06-02T23:50:00+00:00'),
        );

        $this->assertTrue($exception->overlapsTimeSlot($evening));
        $this->assertFalse($exception->overlapsTimeSlot($eveningNextDay));
    }

    public function testNonAllDayExceptionKeepsTheSubmittedRange(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: new DateTimeImmutable('2026-06-01T10:00:00-04:00'),
            endDateTime: new DateTimeImmutable('2026-06-01T12:00:00-04:00'),
            now: new DateTimeImmutable('2026-05-01T00:00:00+00:00'),
            practiceTimeZone: self::practiceTimeZone(),
            isAllDay: false,
        );

        self::assertInstantIs('2026-06-01T14:00:00+00:00', $exception->getStartDateTime());
        self::assertInstantIs('2026-06-01T16:00:00+00:00', $exception->getEndDateTime());
    }

    public function testMultiDayAllDayExceptionSnapsBothEnds(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: new DateTimeImmutable('2026-06-01T10:00:00-04:00'),
            endDateTime: new DateTimeImmutable('2026-06-03T17:00:00-04:00'),
            now: new DateTimeImmutable('2026-05-01T00:00:00+00:00'),
            practiceTimeZone: self::practiceTimeZone(),
            isAllDay: true,
        );

        self::assertInstantIs('2026-06-01T04:00:00+00:00', $exception->getStartDateTime());
        self::assertInstantIs('2026-06-04T04:00:00+00:00', $exception->getEndDateTime());
    }

    public function testAllDayExceptionAlreadyOnPracticeMidnightsIsLeftAlone(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: new DateTimeImmutable('2026-06-01T00:00:00-04:00'),
            endDateTime: new DateTimeImmutable('2026-06-02T00:00:00-04:00'),
            now: new DateTimeImmutable('2026-05-01T00:00:00+00:00'),
            practiceTimeZone: self::practiceTimeZone(),
            isAllDay: true,
        );

        self::assertInstantIs('2026-06-01T04:00:00+00:00', $exception->getStartDateTime());
        self::assertInstantIs('2026-06-02T04:00:00+00:00', $exception->getEndDateTime());
    }

    // --- overlapsTimeSlot ---

    public function testOverlapsTimeSlotWhenOverlapping(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: new DateTimeImmutable('2026-04-01 10:00'),
            endDateTime: new DateTimeImmutable('2026-04-01 12:00'),
            now: new DateTimeImmutable(),
            practiceTimeZone: self::practiceTimeZone(),
        );

        $slot = TimeSlot::fromStartEnd(
            new DateTimeImmutable('2026-04-01 11:00'),
            new DateTimeImmutable('2026-04-01 11:50'),
        );

        $this->assertTrue($exception->overlapsTimeSlot($slot));
    }

    public function testOverlapsTimeSlotWhenPartiallyOverlapping(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: new DateTimeImmutable('2026-04-01 10:00'),
            endDateTime: new DateTimeImmutable('2026-04-01 11:00'),
            now: new DateTimeImmutable(),
            practiceTimeZone: self::practiceTimeZone(),
        );

        $slot = TimeSlot::fromStartEnd(
            new DateTimeImmutable('2026-04-01 10:30'),
            new DateTimeImmutable('2026-04-01 11:30'),
        );

        $this->assertTrue($exception->overlapsTimeSlot($slot));
    }

    public function testOverlapsTimeSlotWhenNotOverlapping(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: new DateTimeImmutable('2026-04-01 10:00'),
            endDateTime: new DateTimeImmutable('2026-04-01 11:00'),
            now: new DateTimeImmutable(),
            practiceTimeZone: self::practiceTimeZone(),
        );

        $slot = TimeSlot::fromStartEnd(
            new DateTimeImmutable('2026-04-01 14:00'),
            new DateTimeImmutable('2026-04-01 14:50'),
        );

        $this->assertFalse($exception->overlapsTimeSlot($slot));
    }

    public function testOverlapsTimeSlotWhenAdjacentDoesNotOverlap(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: new DateTimeImmutable('2026-04-01 10:00'),
            endDateTime: new DateTimeImmutable('2026-04-01 11:00'),
            now: new DateTimeImmutable(),
            practiceTimeZone: self::practiceTimeZone(),
        );

        $slot = TimeSlot::fromStartEnd(
            new DateTimeImmutable('2026-04-01 11:00'),
            new DateTimeImmutable('2026-04-01 11:50'),
        );

        $this->assertFalse($exception->overlapsTimeSlot($slot));
    }

    // --- reconstitute ---

    public function testReconstituteRestoresAllProperties(): void
    {
        $id = ExceptionId::generate();
        $therapistId = UserId::generate();
        $start = new DateTimeImmutable('2026-04-01 09:00');
        $end = new DateTimeImmutable('2026-04-01 17:00');
        $createdAt = new DateTimeImmutable('-1 day');

        $exception = ScheduleException::reconstitute(
            id: $id,
            therapistId: $therapistId,
            startDateTime: $start,
            endDateTime: $end,
            reason: 'Vacation',
            isAllDay: true,
            createdAt: $createdAt,
        );

        $this->assertTrue($id->equals($exception->getId()));
        $this->assertTrue($therapistId->equals($exception->getTherapistId()));
        $this->assertSame($start, $exception->getStartDateTime());
        $this->assertSame($end, $exception->getEndDateTime());
        $this->assertSame('Vacation', $exception->getReason());
        $this->assertTrue($exception->isAllDay());
        $this->assertSame($createdAt, $exception->getCreatedAt());
    }
}
