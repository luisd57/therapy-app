<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Shared;

use App\Application\Shared\InstantFormatter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class InstantFormatterTest extends TestCase
{
    public function testRestatesAnOffsetBearingInstantInUtc(): void
    {
        $formatted = InstantFormatter::toAtomUtc(new DateTimeImmutable('2026-06-01T09:00:00-04:00'));

        $this->assertSame('2026-06-01T13:00:00+00:00', $formatted);
    }

    public function testRestatesAnInstantFromAheadOfUtcOnItsOwnCalendarDay(): void
    {
        // UTC+14 is far enough east that the UTC date differs from the caller's.
        $formatted = InstantFormatter::toAtomUtc(new DateTimeImmutable('2026-06-02T04:00:00+14:00'));

        $this->assertSame('2026-06-01T14:00:00+00:00', $formatted);
    }

    public function testLeavesAnInstantAlreadyInUtcUnchanged(): void
    {
        $formatted = InstantFormatter::toAtomUtc(new DateTimeImmutable('2026-06-01T13:00:00+00:00'));

        $this->assertSame('2026-06-01T13:00:00+00:00', $formatted);
    }

    public function testNullPassesThrough(): void
    {
        $this->assertNull(InstantFormatter::toAtomUtc(null));
    }
}
