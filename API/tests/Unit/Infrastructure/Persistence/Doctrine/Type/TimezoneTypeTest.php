<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\User\ValueObject\Timezone;
use App\Infrastructure\Persistence\Doctrine\Type\TimezoneType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TimezoneTypeTest extends TestCase
{
    private TimezoneType $type;
    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new TimezoneType();
        $this->platform = new PostgreSQLPlatform();
    }

    public function testStoresTheIanaIdentifier(): void
    {
        $timezone = Timezone::fromString(' America/Caracas ');

        $this->assertSame('America/Caracas', $this->type->convertToDatabaseValue($timezone, $this->platform));
    }

    public function testStoresARawStringUnchanged(): void
    {
        $this->assertSame('Europe/Madrid', $this->type->convertToDatabaseValue('Europe/Madrid', $this->platform));
    }

    public function testStoresNullAsNull(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testReadsAStoredIdentifierAsATimezone(): void
    {
        $timezone = $this->type->convertToPHPValue('America/Caracas', $this->platform);

        $this->assertInstanceOf(Timezone::class, $timezone);
        $this->assertSame('America/Caracas', $timezone->getValue());
    }

    public function testReadsAnAlreadyConvertedTimezoneAsTheSameValue(): void
    {
        $timezone = $this->type->convertToPHPValue(Timezone::fromString('Europe/Madrid'), $this->platform);

        $this->assertInstanceOf(Timezone::class, $timezone);
        $this->assertSame('Europe/Madrid', $timezone->getValue());
    }

    public function testReadsNullAsNull(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    /** A fixed offset is the value ADR-0001 forbids, so it must not load as a zone. */
    public function testRejectsAStoredFixedOffset(): void
    {
        try {
            $this->type->convertToPHPValue('-04:00', $this->platform);
            $this->fail('Expected a conversion error.');
        } catch (ValueNotConvertible $exception) {
            $this->assertInstanceOf(InvalidArgumentException::class, $exception->getPrevious());
        }
    }
}
