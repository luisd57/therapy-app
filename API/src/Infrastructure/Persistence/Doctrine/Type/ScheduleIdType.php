<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Appointment\ValueObject\ScheduleId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\GuidType;

final class ScheduleIdType extends GuidType
{
    public const string NAME = 'schedule_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ScheduleId
    {
        if ($value === null) {
            return null;
        }

        return ScheduleId::fromString((string) $value);
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
