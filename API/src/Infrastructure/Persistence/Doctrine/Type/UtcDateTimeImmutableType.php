<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Exception;

/**
 * Maps an instant to TIMESTAMP WITH TIME ZONE, always writing and reading UTC.
 * Not the stock type (it drops the offset) and not DATETIMETZ_IMMUTABLE. See ADR 0001.
 */
final class UtcDateTimeImmutableType extends DateTimeImmutableType
{
    public const string NAME = 'utc_datetime_immutable';

    /** Always emitted with an explicit offset so the DB never has to guess. */
    private const string FORMAT = 'Y-m-d H:i:sP';

    private static ?DateTimeZone $utc = null;

    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTimeTzTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone(self::utc())->format(self::FORMAT);
        }

        throw InvalidType::new($value, static::class, ['null', DateTimeImmutable::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone(self::utc());
        }

        try {
            return (new DateTimeImmutable((string) $value))->setTimezone(self::utc());
        } catch (Exception $exception) {
            throw InvalidFormat::new((string) $value, static::class, self::FORMAT, $exception);
        }
    }

    private static function utc(): DateTimeZone
    {
        return self::$utc ??= new DateTimeZone('UTC');
    }
}
