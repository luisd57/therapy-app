<?php

declare(strict_types=1);

namespace App\Tests\PHPStan\Rule;

use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

final class ClockDoubleShape
{
    /** The error for a way of doubling the clock that UnpinnedClockStubRule cannot read. */
    public static function error(string $shape): IdentifierRuleError
    {
        return RuleErrorBuilder::message(sprintf(
            'A ClockInterface double is stubbed with willReturn() only: %s hides the instant from this check.',
            $shape,
        ))
            ->identifier('testConvention.clockDoubleShape')
            ->tip('Use createMock() or createStub() with willReturn(<pinned instant>), or MockClock. See docs/adr/0003. ' . TestScope::DOC)
            ->build();
    }
}
