<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\SecureTokenGenerator;
use PHPUnit\Framework\TestCase;

final class SecureTokenGeneratorTest extends TestCase
{
    private SecureTokenGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SecureTokenGenerator();
    }

    /**
     * These become invitation and password reset tokens, so a repeat is a takeover.
     */
    public function testProducesADistinctValueOnEveryCall(): void
    {
        $tokens = [];
        for ($call = 0; $call < 20; $call++) {
            $tokens[] = $this->generator->generate();
        }

        $this->assertCount(20, array_unique($tokens));
    }

    public function testDefaultsToSixtyFourCharacters(): void
    {
        $this->assertSame(64, strlen($this->generator->generate()));
    }

    public function testProducesLowercaseHexOnly(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $this->generator->generate());
    }

    public function testHonoursAnExplicitLength(): void
    {
        $this->assertSame(48, strlen($this->generator->generate(48)));
    }

    public function testRejectsALengthBelowTheEntropyFloor(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->generator->generate(31);
    }

    public function testAcceptsTheEntropyFloorItself(): void
    {
        $this->assertSame(32, strlen($this->generator->generate(32)));
    }

    /**
     * Current behaviour, pinned rather than endorsed: the length is halved into bytes with intdiv,
     * so an odd request comes back one character short. No caller passes an odd length today.
     */
    public function testRoundsAnOddLengthDown(): void
    {
        $this->assertSame(32, strlen($this->generator->generate(33)));
    }
}
