<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\User\ValueObject\Phone;
use App\Infrastructure\Persistence\Doctrine\Type\PhoneType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PhoneTypeTest extends TestCase
{
    private PhoneType $type;
    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new PhoneType();
        $this->platform = new PostgreSQLPlatform();
    }

    public function testStoresTheNormalisedNumber(): void
    {
        $phone = Phone::fromString('+58 (412) 555-1234');

        $this->assertSame('+584125551234', $this->type->convertToDatabaseValue($phone, $this->platform));
    }

    public function testStoresARawStringUnchanged(): void
    {
        $this->assertSame('+584125551234', $this->type->convertToDatabaseValue('+584125551234', $this->platform));
    }

    public function testStoresNullAsNull(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testReadsAStoredNumberAsAPhone(): void
    {
        $phone = $this->type->convertToPHPValue('+584125551234', $this->platform);

        $this->assertInstanceOf(Phone::class, $phone);
        $this->assertSame('+584125551234', $phone->getValue());
    }

    public function testReadsAnAlreadyConvertedPhoneAsTheSameValue(): void
    {
        $phone = $this->type->convertToPHPValue(Phone::fromString('+584125551234'), $this->platform);

        $this->assertInstanceOf(Phone::class, $phone);
        $this->assertSame('+584125551234', $phone->getValue());
    }

    public function testReadsNullAsNull(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testRejectsAMalformedStoredValue(): void
    {
        try {
            $this->type->convertToPHPValue('12', $this->platform);
            $this->fail('Expected a conversion error.');
        } catch (ValueNotConvertible $exception) {
            $this->assertInstanceOf(InvalidArgumentException::class, $exception->getPrevious());
        }
    }
}
