<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\User\Id\TokenId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\GuidType;
use InvalidArgumentException;

final class TokenIdType extends GuidType
{
    public const string NAME = 'token_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?TokenId
    {
        if ($value === null) {
            return null;
        }

        try {
            return TokenId::fromString((string) $value);
        } catch (InvalidArgumentException $exception) {
            throw ValueNotConvertible::new($value, static::class, $exception->getMessage(), $exception);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof TokenId) {
            return $value->getValue();
        }

        return (string) $value;
    }
}
