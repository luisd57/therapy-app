<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Persistence\Doctrine\Type\EmailType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EmailTypeTest extends TestCase
{
    private EmailType $type;
    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new EmailType();
        $this->platform = new PostgreSQLPlatform();
    }

    public function testStoresTheNormalisedAddress(): void
    {
        $email = Email::fromString(' Foo@Example.COM ');

        $this->assertSame('foo@example.com', $this->type->convertToDatabaseValue($email, $this->platform));
    }

    public function testStoresARawStringUnchanged(): void
    {
        $this->assertSame('foo@example.com', $this->type->convertToDatabaseValue('foo@example.com', $this->platform));
    }

    public function testStoresNullAsNull(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testReadsAStoredAddressAsAnEmail(): void
    {
        $email = $this->type->convertToPHPValue('foo@example.com', $this->platform);

        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame('foo@example.com', $email->getValue());
    }

    public function testReadsAnAlreadyConvertedEmailAsTheSameValue(): void
    {
        $email = $this->type->convertToPHPValue(Email::fromString('foo@example.com'), $this->platform);

        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame('foo@example.com', $email->getValue());
    }

    public function testReadsNullAsNull(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testRejectsAMalformedStoredValue(): void
    {
        try {
            $this->type->convertToPHPValue('not-an-email', $this->platform);
            $this->fail('Expected a conversion error.');
        } catch (ValueNotConvertible $exception) {
            $this->assertInstanceOf(InvalidArgumentException::class, $exception->getPrevious());
        }
    }
}
