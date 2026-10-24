<?php

declare(strict_types=1);

namespace App\Tests\Helper;

/**
 * Addresses Symfony's Email rule accepts but Email::fromString refuses. Shared so every email
 * field's tests prove the 422 follows the value object, not the framework rule.
 */
final class RefusedEmails
{
    /** @return iterable<string, array{string}> */
    public static function cases(): iterable
    {
        // Three 63-char labels keep the domain well-formed, so only the total length is wrong.
        $domain = str_repeat('a', 63) . '.' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.com';

        yield 'over 254 characters' => [str_repeat('x', 59) . '@' . $domain];
        yield 'local part over 64 characters' => [str_repeat('x', 65) . '@example.com'];
        yield 'two dots in a row' => ['a..b@example.com'];
        yield 'leading dot' => ['.a@example.com'];
        yield 'trailing dot' => ['a.@example.com'];
    }
}
