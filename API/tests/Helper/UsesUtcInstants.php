<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use DateTimeImmutable;
use DateTimeZone;

trait UsesUtcInstants
{
    /** A fixture instant, read as UTC rather than the process zone. */
    protected static function utc(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('UTC'));
    }

    /**
     * Assert an instant, restated in UTC, against a hand-written literal.
     * Formatting the object under test to build the expectation is what ADR-0003 forbids.
     */
    protected static function assertInstantIs(string $expectedUtc, DateTimeImmutable $actual): void
    {
        self::assertSame(
            $expectedUtc,
            $actual->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP'),
        );
    }
}
