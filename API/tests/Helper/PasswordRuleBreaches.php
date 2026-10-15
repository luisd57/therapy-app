<?php

declare(strict_types=1);

namespace App\Tests\Helper;

/**
 * One password per rule, each breaking only that rule, with the message it must produce. Shared
 * so every caller's tests fail when any single rule is dropped.
 */
final class PasswordRuleBreaches
{
    /** @return iterable<string, array{string, string}> */
    public static function cases(): iterable
    {
        yield 'one under the minimum length' => ['Ab1!efg', 'Password must be between 8 and 72 characters'];
        yield 'one over the maximum length' => [str_pad('Ab1!', 73, 'x'), 'Password must be between 8 and 72 characters'];
        yield 'no uppercase letter' => ['ab1!efgh', 'Password must contain at least one uppercase letter'];
        yield 'no lowercase letter' => ['AB1!EFGH', 'Password must contain at least one lowercase letter'];
        yield 'no digit' => ['Abc!efgh', 'Password must contain at least one number'];
        yield 'no special character' => ['Abc1efgh', 'Password must contain at least one special character'];
    }
}
