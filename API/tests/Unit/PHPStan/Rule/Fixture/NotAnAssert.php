<?php

declare(strict_types=1);

namespace App\Tests\Unit\PHPStan\Rule\Fixture;

final class NotAnAssert
{
    public function assertEquals(int $a, int $b): void
    {
    }

    public static function markTestSkipped(): void
    {
    }
}
