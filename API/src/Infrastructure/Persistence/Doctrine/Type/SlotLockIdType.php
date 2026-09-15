<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Appointment\Id\SlotLockId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\GuidType;
use InvalidArgumentException;

final class SlotLockIdType extends GuidType
{
    public const string NAME = 'slot_lock_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?SlotLockId
    {
        if ($value === null) {
            return null;
        }

        try {
            return SlotLockId::fromString((string) $value);
        } catch (InvalidArgumentException $exception) {
            throw ValueNotConvertible::new($value, static::class, $exception->getMessage(), $exception);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SlotLockId) {
            return $value->getValue();
        }

        return (string) $value;
    }
}
