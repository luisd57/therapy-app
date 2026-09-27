<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Appointment\Id\AppointmentId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\GuidType;
use InvalidArgumentException;

final class AppointmentIdType extends GuidType
{
    use ReadsStringValue;

    public const string NAME = 'appointment_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AppointmentId
    {
        if ($value === null) {
            return null;
        }

        try {
            return AppointmentId::fromString(self::stringValue($value));
        } catch (InvalidArgumentException $exception) {
            throw ValueNotConvertible::new($value, static::class, $exception->getMessage(), $exception);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof AppointmentId) {
            return $value->getValue();
        }

        return self::stringValue($value);
    }
}
