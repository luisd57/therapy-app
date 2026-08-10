<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the deliberately hostile suite timezone declared in phpunit.xml.dist.
 *
 * Production runs UTC. If the suite also ran UTC, code that builds a DateTime
 * without an explicit zone would pass by coincidence — UTC in, UTC out. Running
 * at +14:00 (and a day ahead) means any implicit-local assumption produces a
 * visibly wrong instant instead.
 *
 * If this test fails, the ini setting stopped taking effect and every other
 * timezone test in the suite quietly got weaker. Fix the configuration; do not
 * delete this test.
 */
final class TimezoneGuardTest extends TestCase
{
    public function testSuiteRunsInAHostileNonUtcTimezone(): void
    {
        $this->assertSame('Pacific/Kiritimati', date_default_timezone_get());
    }

    public function testHostileTimezoneIsAheadOfUtcSoImplicitLocalBugsShowUp(): void
    {
        $instant = new \DateTimeImmutable('2026-06-01 00:00:00');

        $this->assertSame('+14:00', $instant->format('P'));
        $this->assertSame(
            '2026-05-31',
            $instant->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d'),
            'A naive local midnight must land on the previous UTC day.',
        );
    }
}
