<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use PHPUnit\Framework\Assert;

/**
 * Typed reads into decoded JSON. Each step fails the test when it is not an array or lacks the key.
 */
final class Json
{
    public static function at(mixed $value, int|string ...$path): mixed
    {
        foreach ($path as $key) {
            Assert::assertIsArray($value, sprintf('Expected an array before key "%s".', $key));
            Assert::assertArrayHasKey($key, $value);
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @return array<mixed>
     */
    public static function arrayAt(mixed $value, int|string ...$path): array
    {
        $value = self::at($value, ...$path);
        Assert::assertIsArray($value);

        return $value;
    }

    public static function stringAt(mixed $value, int|string ...$path): string
    {
        $value = self::at($value, ...$path);
        Assert::assertIsString($value);

        return $value;
    }
}
