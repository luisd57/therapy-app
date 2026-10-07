<?php

declare(strict_types=1);

namespace App\Tests\Unit\PHPStan\Rule\Fixture;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

final class BannedAssertions extends TestCase
{
    public function testBanned(): void
    {
        $this->assertEquals(1, 1);
        self::assertEquals(1, 1);
        static::assertNotEquals(1, 2);
        Assert::assertEquals(1, 1);
        $this->ASSERTEQUALS(1, 1);
    }

    // One skip per method: a skip returns never, and PHPStan does not visit the dead code after it.
    public function testSkipped(): void
    {
        $this->markTestSkipped('later');
    }

    public function testIncomplete(): void
    {
        self::markTestIncomplete('later');
    }

    public function testAllowed(): void
    {
        $this->assertEqualsCanonicalizing([1], [1]);
        $this->assertSame(1, 1);
        (new NotAnAssert())->assertEquals(1, 1);
        NotAnAssert::markTestSkipped();
    }
}
