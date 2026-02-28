<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\User\ValueObject\Phone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

final class PhoneType extends StringType
{
    public const string NAME = 'phone';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Phone
    {
        if ($value === null) {
            return null;
        }

        return Phone::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Phone) {
            return $value->getValue();
        }

        return (string) $value;
    }
}
