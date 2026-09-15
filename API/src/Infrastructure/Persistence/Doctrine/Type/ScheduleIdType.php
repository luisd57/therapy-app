<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Appointment\Id\ScheduleId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\GuidType;
use InvalidArgumentException;

final class ScheduleIdType extends GuidType
{
    public const string NAME = 'schedule_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ScheduleId
    {
        if ($value === null) {
            return null;
        }

        try {
            return ScheduleId::fromString((string) $value);
        } catch (InvalidArgumentException $exception) {
            throw ValueNotConvertible::new($value, static::class, $exception->getMessage(), $exception);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ScheduleId) {
            return $value->getValue();
        }

        return (string) $value;
    }
}
