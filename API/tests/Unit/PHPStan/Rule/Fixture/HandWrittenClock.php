<?php

declare(strict_types=1);

namespace App\Tests\Unit\PHPStan\Rule\Fixture;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Clock\ClockInterface;

final class HandWrittenClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    public function sleep(float|int $seconds): void
    {
    }

    public function withTimeZone(DateTimeZone|string $timezone): static
    {
        return $this;
    }

    public static function anonymous(): ClockInterface
    {
        return new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable();
            }

            public function sleep(float|int $seconds): void
            {
            }

            public function withTimeZone(DateTimeZone|string $timezone): static
            {
                return $this;
            }
        };
    }
}
