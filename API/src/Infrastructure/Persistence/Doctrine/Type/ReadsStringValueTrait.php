<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Types\Exception\InvalidType;

trait ReadsStringValueTrait
{
    /** The value as a string, or InvalidType when it is neither a string nor Stringable. */
    private static function stringValue(mixed $value): string
    {
        if (is_string($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        throw InvalidType::new($value, static::class, ['null', 'string']);
    }
}
