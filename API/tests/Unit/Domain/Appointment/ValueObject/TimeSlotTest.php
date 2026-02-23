<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Appointment\ValueObject;

use App\Domain\Appointment\ValueObject\TimeSlot;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TimeSlotTest extends TestCase
{
    public function testCreateSetsStartAndEndCorrectly(): void
    {
        $start = new DateTimeImmutable('2026-03-10 09:00');
        $slot = TimeSlot::create($start, 50);

        $this->assertSame('2026-03-10 09:00', $slot->getStartTime()->format('Y-m-d H:i'));
        $this->assertSame('2026-03-10 09:50', $slot->getEndTime()->format('Y-m-d H:i'));
    }

    public function testCreateWithZeroDurationThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 0);
    }

    public function testCreateWithNegativeDurationThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), -10);
    }

    public function testFromStartEndCreatesSlot(): void
    {
        $start = new DateTimeImmutable('2026-03-10 14:00');
        $end = new DateTimeImmutable('2026-03-10 15:00');

        $slot = TimeSlot::fromStartEnd($start, $end);

        $this->assertSame('2026-03-10 14:00', $slot->getStartTime()->format('Y-m-d H:i'));
        $this->assertSame('2026-03-10 15:00', $slot->getEndTime()->format('Y-m-d H:i'));
    }

    public function testFromStartEndWithEndBeforeStartThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TimeSlot::fromStartEnd(
            new DateTimeImmutable('2026-03-10 15:00'),
            new DateTimeImmutable('2026-03-10 14:00'),
        );
    }

    public function testFromStartEndWithEqualTimesThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $time = new DateTimeImmutable('2026-03-10 14:00');
        TimeSlot::fromStartEnd($time, $time);
    }

    public function testGetDurationMinutesReturnsCorrectValue(): void
    {
        $slot = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 50);

        $this->assertSame(50, $slot->getDurationMinutes());
    }

    public function testGetDurationMinutesFromStartEnd(): void
    {
        $slot = TimeSlot::fromStartEnd(
            new DateTimeImmutable('2026-03-10 09:00'),
            new DateTimeImmutable('2026-03-10 10:30'),
        );

        $this->assertSame(90, $slot->getDurationMinutes());
    }

    public function testOverlappingSlotsReturnTrue(): void
    {
        $slot1 = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 60);
        $slot2 = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:30'), 60);

        $this->assertTrue($slot1->overlaps($slot2));
        $this->assertTrue($slot2->overlaps($slot1));
    }

    public function testNonOverlappingSlotsReturnFalse(): void
    {
        $slot1 = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 60);
        $slot2 = TimeSlot::create(new DateTimeImmutable('2026-03-10 11:00'), 60);

        $this->assertFalse($slot1->overlaps($slot2));
        $this->assertFalse($slot2->overlaps($slot1));
    }

    public function testAdjacentSlotsDoNotOverlap(): void
    {
        $slot1 = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 60);
        $slot2 = TimeSlot::create(new DateTimeImmutable('2026-03-10 10:00'), 60);

        $this->assertFalse($slot1->overlaps($slot2));
        $this->assertFalse($slot2->overlaps($slot1));
    }

    public function testContainedSlotOverlaps(): void
    {
        $outer = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 120);
        $inner = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:30'), 30);

        $this->assertTrue($outer->overlaps($inner));
        $this->assertTrue($inner->overlaps($outer));
    }

    public function testContainsTimePointInsideSlot(): void
    {
        $slot = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 60);
        $inside = new DateTimeImmutable('2026-03-10 09:30');

        $this->assertTrue($slot->contains($inside));
    }

    public function testContainsTimePointAtStartIsInside(): void
    {
        $slot = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 60);
        $atStart = new DateTimeImmutable('2026-03-10 09:00');

        $this->assertTrue($slot->contains($atStart));
    }

    public function testContainsTimePointAtEndIsOutside(): void
    {
        $slot = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 60);
        $atEnd = new DateTimeImmutable('2026-03-10 10:00');

        $this->assertFalse($slot->contains($atEnd));
    }

    public function testContainsTimePointOutsideSlot(): void
    {
        $slot = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 60);
        $outside = new DateTimeImmutable('2026-03-10 11:00');

        $this->assertFalse($slot->contains($outside));
    }

    public function testContainsTimePointBeforeSlot(): void
    {
        $slot = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 60);
        $before = new DateTimeImmutable('2026-03-10 08:00');

        $this->assertFalse($slot->contains($before));
    }

    public function testEqualsReturnsTrueForIdenticalSlots(): void
    {
        $slot1 = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 60);
        $slot2 = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 60);

        $this->assertTrue($slot1->equals($slot2));
    }

    public function testEqualsReturnsFalseForDifferentSlots(): void
    {
        $slot1 = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 60);
        $slot2 = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 50);

        $this->assertFalse($slot1->equals($slot2));
    }

    public function testToStringReturnsFormattedRange(): void
    {
        $slot = TimeSlot::create(new DateTimeImmutable('2026-03-10 09:00'), 60);

        $this->assertSame('2026-03-10 09:00 - 10:00', (string) $slot);
    }
}
