<?php

declare(strict_types=1);

namespace App\Tests\Unit\PHPStan\Rule;

use App\Tests\PHPStan\Rule\UnpinnedClockStubRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<UnpinnedClockStubRule> */
final class UnpinnedClockStubRuleTest extends RuleTestCase
{
    private const string DOC = 'See docs/adr/0003. See "Enforced by PHPStan" in .claude/rules/api-testing.md.';

    protected function getRule(): Rule
    {
        return new UnpinnedClockStubRule();
    }

    public function testAStubReturningTheRealInstantIsReported(): void
    {
        $message = 'A ClockInterface double must return a pinned instant, not the real one.';
        $tip = "Return a literal, for example self::utc('2026-06-15 12:00:00'). " . self::DOC;
        $shapeTip = 'Use createMock() or createStub() with willReturn(<pinned instant>), or MockClock. ' . self::DOC;

        $this->analyse([__DIR__ . '/Fixture/ClockStubs.php'], [
            [$message, 18, $tip],
            [$message, 19, $tip],
            [$message, 20, $tip],
            [$message, 21, $tip],
            [$message, 22, $tip],
            [$message, 23, $tip],
            [$message, 24, $tip],
            [$message, 25, $tip],
            [$message, 26, $tip],
            [$message, 27, $tip],
            ['A ClockInterface double is stubbed with willReturn() only: willReturnCallback() hides the instant from this check.', 33, $shapeTip],
            ['A ClockInterface double is stubbed with willReturn() only: createConfiguredMock() hides the instant from this check.', 34, $shapeTip],
            ['A ClockInterface double is stubbed with willReturn() only: createConfiguredStub() hides the instant from this check.', 35, $shapeTip],
        ]);
    }

    public function testCodeOutsideTheTestNamespaceIsLeftAlone(): void
    {
        $this->analyse([__DIR__ . '/Fixture/outside-tests.php'], []);
    }
}
