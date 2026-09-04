<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\BcryptPasswordHasher;
use PHPUnit\Framework\TestCase;

final class BcryptPasswordHasherTest extends TestCase
{
    // Cheap cost for the round trips. The production cost is pinned once, at the bottom.
    private const int TEST_COST = 4;

    public function testVerifiesTheHashItProduced(): void
    {
        $hasher = $this->hasher();

        $this->assertTrue($hasher->verify('Secure1!pass', $hasher->hash('Secure1!pass')));
    }

    public function testRejectsAWrongPassword(): void
    {
        $hasher = $this->hasher();

        $this->assertFalse($hasher->verify('Wrong1!pass', $hasher->hash('Secure1!pass')));
    }

    public function testRejectsAPasswordDifferingOnlyInCase(): void
    {
        $hasher = $this->hasher();

        $this->assertFalse($hasher->verify('secure1!PASS', $hasher->hash('Secure1!pass')));
    }

    public function testNeverStoresThePasswordItself(): void
    {
        $hash = $this->hasher()->hash('Secure1!pass');

        $this->assertNotSame('Secure1!pass', $hash);
        $this->assertStringNotContainsString('Secure1!pass', $hash);
    }

    /**
     * Same input, different hash: two accounts sharing a password must not share a digest.
     */
    public function testSaltsEachHash(): void
    {
        $hasher = $this->hasher();

        $first = $hasher->hash('Secure1!pass');
        $second = $hasher->hash('Secure1!pass');

        $this->assertNotSame($first, $second);
        $this->assertTrue($hasher->verify('Secure1!pass', $first));
        $this->assertTrue($hasher->verify('Secure1!pass', $second));
    }

    public function testProducesABcryptHash(): void
    {
        $hash = $this->hasher()->hash('Secure1!pass');

        $this->assertSame(PASSWORD_BCRYPT, password_get_info($hash)['algo']);
    }

    /**
     * A cost that is neither the default nor TEST_COST, so the argument is proven to be read.
     */
    public function testAppliesTheConfiguredCost(): void
    {
        $hash = $this->hasher(5)->hash('Secure1!pass');

        $this->assertSame(5, password_get_info($hash)['options']['cost']);
    }

    /**
     * services.yaml passes no $cost, so the default is what production runs. Deliberately slow.
     */
    public function testDefaultsToCostTwelve(): void
    {
        $hash = (new BcryptPasswordHasher())->hash('Secure1!pass');

        $this->assertSame(12, password_get_info($hash)['options']['cost']);
    }

    private function hasher(int $cost = self::TEST_COST): BcryptPasswordHasher
    {
        return new BcryptPasswordHasher($cost);
    }
}
