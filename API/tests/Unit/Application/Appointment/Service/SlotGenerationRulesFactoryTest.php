<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Service;

use App\Application\Appointment\Service\SlotGenerationRulesFactory;
use App\Infrastructure\Config\EnvPracticeTimezoneProvider;
use PHPUnit\Framework\TestCase;

final class SlotGenerationRulesFactoryTest extends TestCase
{
    // Duration and increment differ, as the configured values do. Equal values
    // would let a swapped pair of constructor arguments pass unnoticed.
    private const DURATION_MINUTES = 50;
    private const START_INCREMENT_MINUTES = 30;

    private function createFactory(): SlotGenerationRulesFactory
    {
        return new SlotGenerationRulesFactory(
            new EnvPracticeTimezoneProvider('America/Caracas'),
            self::DURATION_MINUTES,
            self::START_INCREMENT_MINUTES,
        );
    }

    public function testDurationAndStartIncrementReachTheRulesUnswapped(): void
    {
        $slotGenerationRules = $this->createFactory()->create();

        $this->assertSame(self::DURATION_MINUTES, $slotGenerationRules->durationMinutes);
        $this->assertSame(self::START_INCREMENT_MINUTES, $slotGenerationRules->startIncrementMinutes);
    }

    public function testPracticeTimeZoneComesFromTheProvider(): void
    {
        $slotGenerationRules = $this->createFactory()->create();

        $this->assertSame('America/Caracas', $slotGenerationRules->practiceTimeZone->getName());
    }
}
