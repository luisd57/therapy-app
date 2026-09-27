<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Appointment\Enum;

use App\Domain\Appointment\Enum\WeekDay;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WeekDayTest extends TestCase
{
    /** The backing values are stored, so they are a contract: ISO-8601, Monday first. */
    public function testBackingValuesRunMondayToSunday(): void
    {
        $namesByValue = [];
        foreach (WeekDay::cases() as $day) {
            $namesByValue[$day->value] = $day->name;
        }

        // @phpstan-ignore method.alreadyNarrowedType (the analyser reads the enum, the assertion still pins the stored values)
        $this->assertSame(
            [1 => 'MONDAY', 2 => 'TUESDAY', 3 => 'WEDNESDAY', 4 => 'THURSDAY', 5 => 'FRIDAY', 6 => 'SATURDAY', 7 => 'SUNDAY'],
            $namesByValue,
        );
    }

    public function testFromDateTimeImmutableMonday(): void
    {
        // 2026-03-09 is a Monday
        $date = new DateTimeImmutable('2026-03-09');
        $this->assertSame(WeekDay::MONDAY, WeekDay::fromDateTimeImmutable($date));
    }

    public function testFromDateTimeImmutableTuesday(): void
    {
        // 2026-03-10 is a Tuesday
        $date = new DateTimeImmutable('2026-03-10');
        $this->assertSame(WeekDay::TUESDAY, WeekDay::fromDateTimeImmutable($date));
    }

    public function testFromDateTimeImmutableWednesday(): void
    {
        // 2026-03-11 is a Wednesday
        $date = new DateTimeImmutable('2026-03-11');
        $this->assertSame(WeekDay::WEDNESDAY, WeekDay::fromDateTimeImmutable($date));
    }

    public function testFromDateTimeImmutableThursday(): void
    {
        // 2026-03-12 is a Thursday
        $date = new DateTimeImmutable('2026-03-12');
        $this->assertSame(WeekDay::THURSDAY, WeekDay::fromDateTimeImmutable($date));
    }

    public function testFromDateTimeImmutableFriday(): void
    {
        // 2026-03-13 is a Friday
        $date = new DateTimeImmutable('2026-03-13');
        $this->assertSame(WeekDay::FRIDAY, WeekDay::fromDateTimeImmutable($date));
    }

    public function testFromDateTimeImmutableSaturday(): void
    {
        // 2026-03-14 is a Saturday
        $date = new DateTimeImmutable('2026-03-14');
        $this->assertSame(WeekDay::SATURDAY, WeekDay::fromDateTimeImmutable($date));
    }

    public function testFromDateTimeImmutableSunday(): void
    {
        // 2026-03-15 is a Sunday
        $date = new DateTimeImmutable('2026-03-15');
        $this->assertSame(WeekDay::SUNDAY, WeekDay::fromDateTimeImmutable($date));
    }

    public function testGetDisplayNameForAllDays(): void
    {
        $this->assertSame('Monday', WeekDay::MONDAY->getDisplayName());
        $this->assertSame('Tuesday', WeekDay::TUESDAY->getDisplayName());
        $this->assertSame('Wednesday', WeekDay::WEDNESDAY->getDisplayName());
        $this->assertSame('Thursday', WeekDay::THURSDAY->getDisplayName());
        $this->assertSame('Friday', WeekDay::FRIDAY->getDisplayName());
        $this->assertSame('Saturday', WeekDay::SATURDAY->getDisplayName());
        $this->assertSame('Sunday', WeekDay::SUNDAY->getDisplayName());
    }
}
