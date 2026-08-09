<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\User\ValueObject\Timezone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

final class TimezoneType extends StringType
{
    public const string NAME = 'timezone';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Timezone
    {
        if ($value === null) {
            return null;
        }

        return Timezone::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Timezone) {
            return $value->getValue();
        }

        return (string) $value;
    }
}
