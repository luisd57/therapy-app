<?php

declare(strict_types=1);

namespace App\Tests\Unit\PHPStan\Rule;

use App\Tests\PHPStan\Rule\HandWrittenClockRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<HandWrittenClockRule> */
final class HandWrittenClockRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new HandWrittenClockRule();
    }

    public function testAClockClassInTheSuiteIsReported(): void
    {
        $message = 'A ClockInterface double is stubbed with willReturn() only: a hand-written clock class hides the instant from this check.';
        $tip = 'Use createMock() or createStub() with willReturn(<pinned instant>), or MockClock. '
            . 'See docs/adr/0003. See "Enforced by PHPStan" in .claude/rules/api-testing.md.';

        $this->analyse([__DIR__ . '/Fixture/HandWrittenClock.php'], [
            [$message, 11, $tip],
            [$message, 29, $tip],
        ]);
    }

    public function testOtherClassesAreLeftAlone(): void
    {
        $this->analyse([__DIR__ . '/Fixture/NotAnAssert.php'], []);
    }
}
