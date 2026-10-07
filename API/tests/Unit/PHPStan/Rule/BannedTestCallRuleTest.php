<?php

declare(strict_types=1);

namespace App\Tests\Unit\PHPStan\Rule;

use App\Tests\PHPStan\Rule\BannedTestCallRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<BannedTestCallRule> */
final class BannedTestCallRuleTest extends RuleTestCase
{
    private const string DOC = 'See "Enforced by PHPStan" in .claude/rules/api-testing.md.';

    protected function getRule(): Rule
    {
        return new BannedTestCallRule();
    }

    public function testSleepingIsReported(): void
    {
        $tip = 'Pin time with freezeClock() or a stubbed ClockInterface. ' . self::DOC;

        $this->analyse([__DIR__ . '/Fixture/banned-functions.php'], [
            ['sleep() is banned in tests: waiting on the wall clock is slow and flaky.', 9, $tip],
            ['usleep() is banned in tests: waiting on the wall clock is slow and flaky.', 10, $tip],
            ['sleep() is banned in tests: waiting on the wall clock is slow and flaky.', 11, $tip],
        ]);
    }

    public function testBannedAssertionsAndSkipsAreReported(): void
    {
        $equals = 'it compares dates by instant and hides a wrong offset.';
        $equalsTip = 'Use assertSame(), or assertInstantIs() for an instant. See docs/adr/0003. ' . self::DOC;
        $skip = 'a skipped test lets the suite shrink without failing.';
        $skipTip = 'Fix the test or delete it. ' . self::DOC;

        $this->analyse([__DIR__ . '/Fixture/BannedAssertions.php'], [
            ['assertEquals() is banned in tests: ' . $equals, 14, $equalsTip],
            ['assertEquals() is banned in tests: ' . $equals, 15, $equalsTip],
            ['assertNotEquals() is banned in tests: ' . $equals, 16, $equalsTip],
            ['assertEquals() is banned in tests: ' . $equals, 17, $equalsTip],
            ['assertEquals() is banned in tests: ' . $equals, 18, $equalsTip],
            ['markTestSkipped() is banned in tests: ' . $skip, 24, $skipTip],
            ['markTestIncomplete() is banned in tests: ' . $skip, 29, $skipTip],
        ]);
    }

    public function testCodeOutsideTheTestNamespaceIsLeftAlone(): void
    {
        $this->analyse([__DIR__ . '/Fixture/outside-tests.php'], []);
    }

    public function testTheRootTestNamespaceIsCoveredAndALookalikeIsNot(): void
    {
        $tip = 'Pin time with freezeClock() or a stubbed ClockInterface. ' . self::DOC;

        $this->analyse([__DIR__ . '/Fixture/tests-root-namespace.php'], [
            ['sleep() is banned in tests: waiting on the wall clock is slow and flaky.', 9, $tip],
        ]);
        $this->analyse([__DIR__ . '/Fixture/lookalike-namespace.php'], []);
    }
}
