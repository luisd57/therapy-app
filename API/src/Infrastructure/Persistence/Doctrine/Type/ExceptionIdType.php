<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Appointment\Id\ExceptionId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\GuidType;
use InvalidArgumentException;

final class ExceptionIdType extends GuidType
{
    public const string NAME = 'exception_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ExceptionId
    {
        if ($value === null) {
            return null;
        }

        try {
            return ExceptionId::fromString((string) $value);
        } catch (InvalidArgumentException $exception) {
            throw ValueNotConvertible::new($value, static::class, $exception->getMessage(), $exception);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ExceptionId) {
            return $value->getValue();
        }

        return (string) $value;
    }
}
