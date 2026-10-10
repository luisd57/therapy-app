<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Appointment\Service;

use App\Domain\Appointment\Service\SlotGenerationRules;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class SlotGenerationRulesTest extends TestCase
{
    public function testAZeroDurationIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slot duration must be positive.');

        SlotGenerationRules::create(
            practiceTimeZone: new DateTimeZone('America/Caracas'),
            durationMinutes: 0,
            startIncrementMinutes: 30,
        );
    }

    // A zero increment would never advance the grid, so it has to stop here.
    public function testAZeroStartIncrementIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slot start increment must be positive.');

        SlotGenerationRules::create(
            practiceTimeZone: new DateTimeZone('America/Caracas'),
            durationMinutes: 50,
            startIncrementMinutes: 0,
        );
    }
}
