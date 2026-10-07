<?php

declare(strict_types=1);

namespace App\Tests\PHPStan\Rule;

use PHPStan\Analyser\Scope;

final class TestScope
{
    public const string DOC = 'See "Enforced by PHPStan" in .claude/rules/api-testing.md.';

    /** True when the analysed code is in the test suite, the only place these rules apply. */
    public static function covers(Scope $scope): bool
    {
        $namespace = $scope->getNamespace() ?? '';

        return $namespace === 'App\Tests' || str_starts_with($namespace, 'App\Tests\\');
    }
}
