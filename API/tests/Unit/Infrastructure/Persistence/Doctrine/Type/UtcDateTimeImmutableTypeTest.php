<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence\Doctrine\Type;

use App\Infrastructure\Persistence\Doctrine\Type\UtcDateTimeImmutableType;
use App\Tests\Helper\UsesUtcInstants;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UtcDateTimeImmutableTypeTest extends TestCase
{
    use UsesUtcInstants;

    private UtcDateTimeImmutableType $type;
    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new UtcDateTimeImmutableType();
        $this->platform = new PostgreSQLPlatform();
    }

    /** @return iterable<string, array{DateTimeImmutable}> */
    public static function sameInstantInOtherZones(): iterable
    {
        yield 'Caracas' => [new DateTimeImmutable('2026-03-10 10:00:00', new DateTimeZone('America/Caracas'))];
        yield 'Tokyo' => [new DateTimeImmutable('2026-03-10 23:00:00', new DateTimeZone('Asia/Tokyo'))];
        yield 'UTC' => [self::utc('2026-03-10 14:00:00')];
    }

    #[DataProvider('sameInstantInOtherZones')]
    public function testStoresUtcWhateverZoneTheValueCarries(DateTimeImmutable $dateTime): void
    {
        $this->assertSame(
            '2026-03-10 14:00:00+00:00',
            $this->type->convertToDatabaseValue($dateTime, $this->platform),
        );
    }

    public function testStoresNullAsNull(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testRefusesToStoreAMutableDateTime(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToDatabaseValue(new DateTime('2026-03-10 14:00:00+00:00'), $this->platform);
    }

    public function testRefusesToStoreAString(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToDatabaseValue('2026-03-10 14:00:00+00:00', $this->platform);
    }

    #[DataProvider('storedValuesProvider')]
    public function testReadsAStoredValueAsUtc(string $stored): void
    {
        $dateTime = $this->type->convertToPHPValue($stored, $this->platform);

        $this->assertNotNull($dateTime);
        self::assertInstantIs('2026-03-10T14:00:00+00:00', $dateTime);
        $this->assertSame('UTC', $dateTime->getTimezone()->getName());
    }

    /** @return iterable<string, array{string}> */
    public static function storedValuesProvider(): iterable
    {
        yield 'Postgres UTC shape' => ['2026-03-10 14:00:00+00'];
        yield 'another session zone' => ['2026-03-10 10:00:00-04'];
    }

    public function testRestatesAnAlreadyConvertedValueInUtc(): void
    {
        $caracas = new DateTimeImmutable('2026-03-10 10:00:00', new DateTimeZone('America/Caracas'));

        $dateTime = $this->type->convertToPHPValue($caracas, $this->platform);

        $this->assertNotNull($dateTime);
        $this->assertSame('UTC', $dateTime->getTimezone()->getName());
        self::assertInstantIs('2026-03-10T14:00:00+00:00', $dateTime);
    }

    public function testReadsNullAsNull(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testRejectsAMalformedStoredValue(): void
    {
        $this->expectException(InvalidFormat::class);

        $this->type->convertToPHPValue('not a date', $this->platform);
    }
}
