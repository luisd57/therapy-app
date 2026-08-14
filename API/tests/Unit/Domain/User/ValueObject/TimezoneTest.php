<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\ValueObject;

use App\Domain\User\ValueObject\Timezone;
use PHPUnit\Framework\TestCase;

final class TimezoneTest extends TestCase
{
    public function testAcceptsAnIanaIdentifier(): void
    {
        $timezone = Timezone::fromString('America/Caracas');

        $this->assertSame('America/Caracas', $timezone->getValue());
    }

    public function testAcceptsUtc(): void
    {
        $this->assertSame('UTC', Timezone::fromString('UTC')->getValue());
    }

    public function testTrimsSurroundingWhitespace(): void
    {
        $this->assertSame('Europe/Madrid', Timezone::fromString('  Europe/Madrid  ')->getValue());
    }

    /**
     * The whole point of storing a zone rather than an offset: only a named zone
     * carries the DST rules, and Europe is where most patients are.
     */
    public function testConvertsToADateTimeZoneThatTracksDaylightSaving(): void
    {
        $madrid = Timezone::fromString('Europe/Madrid')->toDateTimeZone();

        $winter = new \DateTimeImmutable('2026-01-15 12:00:00', $madrid);
        $summer = new \DateTimeImmutable('2026-07-15 12:00:00', $madrid);

        $this->assertSame('+01:00', $winter->format('P'));
        $this->assertSame('+02:00', $summer->format('P'));
    }

    /**
     * new DateTimeZone('-04:00') is perfectly valid in PHP, so merely constructing
     * one proves nothing - a fixed offset must be rejected explicitly or it would
     * be wrong for half of every DST year.
     */
    public function testRejectsAFixedOffset(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Timezone::fromString('-04:00');
    }

    public function testRejectsAnOffsetStyleAbbreviation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Timezone::fromString('UTC+2');
    }

    public function testRejectsAnUnknownIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Timezone::fromString('Mars/Olympus_Mons');
    }

    public function testRejectsAnEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Timezone::fromString('   ');
    }

    public function testEqualsComparesByValue(): void
    {
        $this->assertTrue(
            Timezone::fromString('America/Caracas')->equals(Timezone::fromString('America/Caracas')),
        );
        $this->assertFalse(
            Timezone::fromString('America/Caracas')->equals(Timezone::fromString('Europe/Madrid')),
        );
    }
}
