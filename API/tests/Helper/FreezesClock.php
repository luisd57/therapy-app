<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

trait FreezesClock
{
    /**
     * Replace the container's ClockInterface with a frozen MockClock so date-dependent
     * tests can pin "now" to a fixed instant. Must be called before the test resolves
     * the clock-using service, which for a console command is before the tester builds it.
     *
     * $now is read as UTC unless it carries its own offset - never as the process
     * timezone, which the suite deliberately sets to something absurd.
     */
    protected function freezeClock(string $now): MockClock
    {
        $clock = new MockClock(new \DateTimeImmutable($now, new \DateTimeZone('UTC')));
        self::getContainer()->set(ClockInterface::class, $clock);

        return $clock;
    }
}
