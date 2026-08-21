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

        // No "already hashed" shortcut: raw tokens are 64 hex chars too, so a
        // shape check on the digest skips hashing every real token.
        return hash('sha256', $value);
    }
}
