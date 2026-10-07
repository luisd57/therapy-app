<?php

declare(strict_types=1);

namespace App\Domain\Fixture;

use DateTimeImmutable;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Clock\ClockInterface;

function everythingTheRulesBan(ClockInterface&MockObject $clock): void
{
    sleep(1);
    Assert::assertEquals(1, 1);
    new DateTimeImmutable('2026-06-02 09:00:00');
    $clock->method('now')->willReturn(new DateTimeImmutable());
}

function handWrittenClock(): ClockInterface
{
    return new class implements ClockInterface {
        public function now(): DateTimeImmutable
        {
            return new DateTimeImmutable();
        }

        public function sleep(float|int $seconds): void
        {
        }

        public function withTimeZone(\DateTimeZone|string $timezone): static
        {
            return $this;
        }
    };
}
