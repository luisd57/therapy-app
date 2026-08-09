<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Config;

use App\Infrastructure\Config\EnvPracticeTimezoneProvider;
use PHPUnit\Framework\TestCase;

final class EnvPracticeTimezoneProviderTest extends TestCase
{
    public function testExposesTheConfiguredZone(): void
    {
        $provider = new EnvPracticeTimezoneProvider('America/Caracas');

        $this->assertSame('America/Caracas', $provider->getTimeZone()->getName());
        $this->assertSame('America/Caracas', $provider->getIdentifier());
    }

    /**
     * Failing in the constructor means a typo in PRACTICE_TIMEZONE breaks the
     * container at boot rather than surfacing on the first slot request.
     */
    public function testRejectsAnInvalidZoneAtConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EnvPracticeTimezoneProvider('Not/AZone');
    }

    public function testRejectsAFixedOffset(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EnvPracticeTimezoneProvider('-04:00');
    }
}
