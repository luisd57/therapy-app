<?php

declare(strict_types=1);

namespace App\Tests\Unit\PHPStan\Rule;

use App\Tests\PHPStan\Rule\NaiveDateTimeLiteralRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<NaiveDateTimeLiteralRule> */
final class NaiveDateTimeLiteralRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NaiveDateTimeLiteralRule();
    }

    public function testALiteralWithNoZoneIsReported(): void
    {
        $tip = "Write the offset into the literal, or use self::utc(). "
            . 'See docs/adr/0003. See "Enforced by PHPStan" in .claude/rules/api-testing.md.';
        $error = static fn (string $literal, int $line): array => [
            sprintf("DateTimeImmutable('%s') has no zone, so it is read in the process timezone.", $literal),
            $line,
            $tip,
        ];

        $this->analyse([__DIR__ . '/Fixture/date-literals.php'], [
            $error('2026-06-02 09:00:00', 14),
            $error('2026-03-09', 15),
            $error('2026-04-01 09:00', 16),
            $error('tomorrow 09:00:00', 17),
            $error('tomorrow', 18),
            $error('noon', 19),
            $error('2026-09-15 09:00:00', 20),
            $error('2026-06-02T09:00:00', 22),
        ]);
    }

    public function testCodeOutsideTheTestNamespaceIsLeftAlone(): void
    {
        $this->analyse([__DIR__ . '/Fixture/outside-tests.php'], []);
    }
}
