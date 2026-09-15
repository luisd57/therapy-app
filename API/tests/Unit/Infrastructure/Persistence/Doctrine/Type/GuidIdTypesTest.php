<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Appointment\Id\AppointmentId;
use App\Domain\Appointment\Id\ExceptionId;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Id\SlotLockId;
use App\Domain\User\Id\TokenId;
use App\Domain\User\Id\UserId;
use App\Infrastructure\Persistence\Doctrine\Type\AppointmentIdType;
use App\Infrastructure\Persistence\Doctrine\Type\ExceptionIdType;
use App\Infrastructure\Persistence\Doctrine\Type\ScheduleIdType;
use App\Infrastructure\Persistence\Doctrine\Type\SlotLockIdType;
use App\Infrastructure\Persistence\Doctrine\Type\TokenIdType;
use App\Infrastructure\Persistence\Doctrine\Type\UserIdType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\GuidType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** One file for all six: the types differ only in the id class, and six copies would drift. */
final class GuidIdTypesTest extends TestCase
{
    private const string UUID = '01890a5d-ac96-774b-bcce-b302099a8057';

    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->platform = new PostgreSQLPlatform();
    }

    /** @return iterable<string, array{GuidType, class-string}> */
    public static function idTypes(): iterable
    {
        yield 'AppointmentIdType' => [new AppointmentIdType(), AppointmentId::class];
        yield 'ExceptionIdType' => [new ExceptionIdType(), ExceptionId::class];
        yield 'ScheduleIdType' => [new ScheduleIdType(), ScheduleId::class];
        yield 'SlotLockIdType' => [new SlotLockIdType(), SlotLockId::class];
        yield 'TokenIdType' => [new TokenIdType(), TokenId::class];
        yield 'UserIdType' => [new UserIdType(), UserId::class];
    }

    /** @param class-string $idClass */
    #[DataProvider('idTypes')]
    public function testStoresTheIdAsItsUuidString(GuidType $type, string $idClass): void
    {
        $this->assertSame(self::UUID, $type->convertToDatabaseValue($idClass::fromString(self::UUID), $this->platform));
    }

    /** @param class-string $idClass */
    #[DataProvider('idTypes')]
    public function testStoresARawUuidStringUnchanged(GuidType $type, string $idClass): void
    {
        $this->assertSame(self::UUID, $type->convertToDatabaseValue(self::UUID, $this->platform));
    }

    /** @param class-string $idClass */
    #[DataProvider('idTypes')]
    public function testStoresNullAsNull(GuidType $type, string $idClass): void
    {
        $this->assertNull($type->convertToDatabaseValue(null, $this->platform));
    }

    /** @param class-string $idClass */
    #[DataProvider('idTypes')]
    public function testReadsAStoredUuidAsTheId(GuidType $type, string $idClass): void
    {
        $id = $type->convertToPHPValue(self::UUID, $this->platform);

        $this->assertInstanceOf($idClass, $id);
        $this->assertSame(self::UUID, $id->getValue());
    }

    /** @param class-string $idClass */
    #[DataProvider('idTypes')]
    public function testReadsAnAlreadyConvertedIdAsTheSameValue(GuidType $type, string $idClass): void
    {
        $id = $type->convertToPHPValue($idClass::fromString(self::UUID), $this->platform);

        $this->assertInstanceOf($idClass, $id);
        $this->assertSame(self::UUID, $id->getValue());
    }

    /** @param class-string $idClass */
    #[DataProvider('idTypes')]
    public function testReadsNullAsNull(GuidType $type, string $idClass): void
    {
        $this->assertNull($type->convertToPHPValue(null, $this->platform));
    }

    /** @param class-string $idClass */
    #[DataProvider('idTypes')]
    public function testRejectsAMalformedStoredValue(GuidType $type, string $idClass): void
    {
        try {
            $type->convertToPHPValue('not-a-uuid', $this->platform);
            $this->fail('Expected a conversion error.');
        } catch (ValueNotConvertible $exception) {
            $this->assertInstanceOf(InvalidArgumentException::class, $exception->getPrevious());
        }
    }
}
