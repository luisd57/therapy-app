<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Appointment\Entity;

use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Id\ExceptionId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Id\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ScheduleExceptionTest extends TestCase
{
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
        );

        $this->assertSame('', $exception->getReason());
    }

    public function testCreateWithAllDayFlag(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: new DateTimeImmutable('2026-04-01 00:00'),
            endDateTime: new DateTimeImmutable('2026-04-02 00:00'),
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
        );
    }

    // --- overlapsTimeSlot ---

    public function testOverlapsTimeSlotWhenOverlapping(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: UserId::generate(),
            startDateTime: new DateTimeImmutable('2026-04-01 10:00'),
            endDateTime: new DateTimeImmutable('2026-04-01 12:00'),
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
