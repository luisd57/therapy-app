<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

final class HashedStringType extends StringType
{
    public const string NAME = 'hashed_string';

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        // If already a SHA-256 hash (64 hex chars), return as-is to prevent double-hashing
        if (strlen($value) === 64 && ctype_xdigit($value)) {
            return $value;
        }

        return hash('sha256', $value);
    }
}
