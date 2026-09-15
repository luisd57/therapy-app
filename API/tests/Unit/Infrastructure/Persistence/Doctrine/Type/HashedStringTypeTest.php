<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence\Doctrine\Type;

use App\Infrastructure\Persistence\Doctrine\Type\HashedStringType;
use App\Infrastructure\Security\SecureTokenGenerator;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;

final class HashedStringTypeTest extends TestCase
{
    private HashedStringType $type;
    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new HashedStringType();
        $this->platform = new PostgreSQLPlatform();
    }

    /**
     * Generator output is 64 hex chars, which an "already hashed" guard would
     * wave through and store in plaintext.
     */
    public function testHashesATokenShapedLikeADigest(): void
    {
        $raw = (new SecureTokenGenerator())->generate();

        $stored = $this->type->convertToDatabaseValue($raw, $this->platform);

        $this->assertNotSame($raw, $stored);
        $this->assertSame(hash('sha256', $raw), $stored);
    }

    public function testHashesAShortToken(): void
    {
        $stored = $this->type->convertToDatabaseValue('short-token', $this->platform);

        $this->assertSame(hash('sha256', 'short-token'), $stored);
    }

    public function testLeavesNullAlone(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testReadsTheStoredDigestUnchanged(): void
    {
        $digest = '0b5b3bc8e6ba2ff02e2ba1a8f6e8b2e1d5c2a0b3f4e6d7c8b9a0f1e2d3c4b5a6';

        $this->assertSame($digest, $this->type->convertToPHPValue($digest, $this->platform));
    }

    public function testReadsNullAsNull(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}
