<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Appointment\ValueObject;

use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Tests\Helper\AssertsInstants;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures name their zone and expectations are hand-written UTC instants. A naive
 * fixture formatted back out of the slot shifts with the process zone on both sides,
 * so it agrees with any implementation. See ADR-0003.
 */
final class TimeSlotTest extends TestCase
{
    use AssertsInstants;

    private static function utc(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('UTC'));
    }

    // --- create() ---

    public function testCreateSetsStartAndEndCorrectly(): void
    {
        $slot = TimeSlot::create(self::utc('2026-03-10 09:00'), 50);

        self::assertInstantIs('2026-03-10T09:00:00+00:00', $slot->getStartTime());
        self::assertInstantIs('2026-03-10T09:50:00+00:00', $slot->getEndTime());
    }

    /**
     * The caller's offset is the only thing saying which instant they meant. Rebuilding
     * the start from its wall-clock digits would resolve it against the process zone,
     * which at UTC+14 lands fourteen hours away.
     */
    public function testCreateKeepsTheInstantTheCallersOffsetNames(): void
    {
        $slot = TimeSlot::create(new DateTimeImmutable('2026-03-10T09:00:00-04:00'), 50);

        self::assertInstantIs('2026-03-10T13:00:00+00:00', $slot->getStartTime());
        self::assertInstantIs('2026-03-10T13:50:00+00:00', $slot->getEndTime());
    }

    public function testCreateWithZeroDurationThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TimeSlot::create(self::utc('2026-03-10 09:00'), 0);
    }

    public function testCreateWithNegativeDurationThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TimeSlot::create(self::utc('2026-03-10 09:00'), -10);
    }

    // --- fromStartEnd() ---

    public function testFromStartEndCreatesSlot(): void
    {
        $slot = TimeSlot::fromStartEnd(
            self::utc('2026-03-10 14:00'),
            self::utc('2026-03-10 15:00'),
        );

        self::assertInstantIs('2026-03-10T14:00:00+00:00', $slot->getStartTime());
        self::assertInstantIs('2026-03-10T15:00:00+00:00', $slot->getEndTime());
    }

    public function testFromStartEndKeepsBothInstantsWhenTheEndsCarryDifferentOffsets(): void
    {
        $slot = TimeSlot::fromStartEnd(
            new DateTimeImmutable('2026-03-10T09:00:00-04:00'),
            new DateTimeImmutable('2026-03-11T04:00:00+14:00'),
        );

        self::assertInstantIs('2026-03-10T13:00:00+00:00', $slot->getStartTime());
        self::assertInstantIs('2026-03-10T14:00:00+00:00', $slot->getEndTime());
    }

    public function testFromStartEndWithEndBeforeStartThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TimeSlot::fromStartEnd(
            self::utc('2026-03-10 15:00'),
            self::utc('2026-03-10 14:00'),
        );
    }

    public function testFromStartEndWithEqualTimesThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $time = self::utc('2026-03-10 14:00');
        TimeSlot::fromStartEnd($time, $time);
    }

    // --- getDurationMinutes() ---

    public function testGetDurationMinutesReturnsCorrectValue(): void
    {
        $slot = TimeSlot::create(self::utc('2026-03-10 09:00'), 50);

        $this->assertSame(50, $slot->getDurationMinutes());
    }

    public function testGetDurationMinutesFromStartEnd(): void
    {
        $slot = TimeSlot::fromStartEnd(
            self::utc('2026-03-10 09:00'),
            self::utc('2026-03-10 10:30'),
        );

        $this->assertSame(90, $slot->getDurationMinutes());
    }

    // --- overlaps() ---

    public function testOverlappingSlotsReturnTrue(): void
    {
        $slot1 = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);
        $slot2 = TimeSlot::create(self::utc('2026-03-10 09:30'), 60);

        $this->assertTrue($slot1->overlaps($slot2));
        $this->assertTrue($slot2->overlaps($slot1));
    }

    public function testNonOverlappingSlotsReturnFalse(): void
    {
        $slot1 = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);
        $slot2 = TimeSlot::create(self::utc('2026-03-10 11:00'), 60);

        $this->assertFalse($slot1->overlaps($slot2));
        $this->assertFalse($slot2->overlaps($slot1));
    }

    public function testAdjacentSlotsDoNotOverlap(): void
    {
        $slot1 = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);
        $slot2 = TimeSlot::create(self::utc('2026-03-10 10:00'), 60);

        $this->assertFalse($slot1->overlaps($slot2));
        $this->assertFalse($slot2->overlaps($slot1));
    }

    public function testContainedSlotOverlaps(): void
    {
        $outer = TimeSlot::create(self::utc('2026-03-10 09:00'), 120);
        $inner = TimeSlot::create(self::utc('2026-03-10 09:30'), 30);

        $this->assertTrue($outer->overlaps($inner));
        $this->assertTrue($inner->overlaps($outer));
    }

    /**
     * The same two instants, one of them written in another offset. Overlap is a
     * question about instants, so the answer cannot change with the wording.
     */
    public function testOverlapIsDecidedOnInstantsNotWallClocks(): void
    {
        $slot = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);
        $sameHourInCaracas = TimeSlot::create(new DateTimeImmutable('2026-03-10T05:30:00-04:00'), 60);

        $this->assertTrue($slot->overlaps($sameHourInCaracas));
    }

    // --- contains() ---

    public function testContainsTimePointInsideSlot(): void
    {
        $slot = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);

        $this->assertTrue($slot->contains(self::utc('2026-03-10 09:30')));
    }

    public function testContainsTimePointAtStartIsInside(): void
    {
        $slot = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);

        $this->assertTrue($slot->contains(self::utc('2026-03-10 09:00')));
    }

    public function testContainsTimePointAtEndIsOutside(): void
    {
        $slot = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);

        $this->assertFalse($slot->contains(self::utc('2026-03-10 10:00')));
    }

    public function testContainsTimePointOutsideSlot(): void
    {
        $slot = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);

        $this->assertFalse($slot->contains(self::utc('2026-03-10 11:00')));
    }

    public function testContainsTimePointBeforeSlot(): void
    {
        $slot = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);

        $this->assertFalse($slot->contains(self::utc('2026-03-10 08:00')));
    }

    public function testContainsTakesTheOffsetOfThePointItIsGiven(): void
    {
        $slot = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);

        // 05:30-04:00 is 09:30 UTC, inside. The same digits read as UTC are outside.
        $this->assertTrue($slot->contains(new DateTimeImmutable('2026-03-10T05:30:00-04:00')));
        $this->assertFalse($slot->contains(self::utc('2026-03-10 05:30')));
    }

    // --- equals() ---

    public function testEqualsReturnsTrueForIdenticalSlots(): void
    {
        $slot1 = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);
        $slot2 = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);

        $this->assertTrue($slot1->equals($slot2));
    }

    public function testEqualsReturnsTrueForTheSameInstantWrittenInAnotherOffset(): void
    {
        $slot1 = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);
        $slot2 = TimeSlot::create(new DateTimeImmutable('2026-03-10T05:00:00-04:00'), 60);

        $this->assertTrue($slot1->equals($slot2));
    }

    public function testEqualsReturnsFalseForDifferentSlots(): void
    {
        $slot1 = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);
        $slot2 = TimeSlot::create(self::utc('2026-03-10 09:00'), 50);

        $this->assertFalse($slot1->equals($slot2));
    }

    // --- __toString() ---

    /**
     * Rendered in the slot's own zone, so the fixture names UTC and the expected
     * string is written out rather than formatted back off the slot.
     */
    public function testToStringReturnsFormattedRange(): void
    {
        $slot = TimeSlot::create(self::utc('2026-03-10 09:00'), 60);

        $this->assertSame('2026-03-10 09:00 - 10:00', (string) $slot);
    }
}
