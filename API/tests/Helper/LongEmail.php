<?php

declare(strict_types=1);

namespace App\Tests\Helper;

/** Builds a well-formed email address of an exact length, for testing the length cap. */
final class LongEmail
{
    public static function ofLength(int $length): string
    {
        // Three 63-char labels: the longest a DNS label may be, so the address stays well-formed.
        $domain = str_repeat('a', 63) . '.' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.com';
        $localLength = $length - 1 - strlen($domain);

        if ($localLength < 1 || $localLength > 64) {
            throw new \InvalidArgumentException(sprintf('Cannot build a well-formed email of length %d.', $length));
        }

        return str_repeat('x', $localLength) . '@' . $domain;
    }
}
