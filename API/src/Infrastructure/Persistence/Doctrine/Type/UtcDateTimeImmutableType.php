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
 *
 * The stock datetime_immutable type formats a value using its OWN timezone and
 * emits no offset, so an instant built in America/Caracas is stored as though it
 * were UTC and silently shifts by four hours. Pinning both directions to UTC
 * means a stored row denotes the same point on the timeline regardless of which
 * zone the writing code happened to be in.
 *
 * Deliberately not DBAL's DATETIMETZ_IMMUTABLE: that type requires the exact
 * 'Y-m-d H:i:sO' format and has no parse fallback, while Postgres renders the
 * offset as '+00' — any drift becomes a hard InvalidFormat at read time.
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
