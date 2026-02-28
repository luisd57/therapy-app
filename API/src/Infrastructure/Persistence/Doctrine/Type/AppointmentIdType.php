<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Appointment\ValueObject\AppointmentId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\GuidType;

final class AppointmentIdType extends GuidType
{
    public const string NAME = 'appointment_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AppointmentId
    {
        if ($value === null) {
            return null;
        }

        return AppointmentId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof AppointmentId) {
            return $value->getValue();
        }

        return (string) $value;
    }
}
