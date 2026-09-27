<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\User\Id\UserId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\GuidType;
use InvalidArgumentException;

final class UserIdType extends GuidType
{
    use ReadsStringValue;

    public const string NAME = 'user_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UserId
    {
        if ($value === null) {
            return null;
        }

        try {
            return UserId::fromString(self::stringValue($value));
        } catch (InvalidArgumentException $exception) {
            throw ValueNotConvertible::new($value, static::class, $exception->getMessage(), $exception);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UserId) {
            return $value->getValue();
        }

        return self::stringValue($value);
    }
}
