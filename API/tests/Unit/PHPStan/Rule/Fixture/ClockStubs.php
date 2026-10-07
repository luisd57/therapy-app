<?php

declare(strict_types=1);

namespace App\Tests\Unit\PHPStan\Rule\Fixture;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;

final class ClockStubs extends TestCase
{
    public function testUnpinned(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable());
        $clock->method('now')->willReturn(new \DateTimeImmutable('now'));
        $clock->expects($this->once())->method('now')->willReturn(new DateTimeImmutable());
        $this->createStub(ClockInterface::class)->method('now')->willReturn(new DateTimeImmutable());
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-06-15T12:00:00+00:00'), new DateTimeImmutable());
    }

    public function testOtherWaysToDoubleTheClock(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturnCallback(static fn () => new DateTimeImmutable('2026-06-15T12:00:00+00:00'));
        $this->createConfiguredMock(ClockInterface::class, ['now' => new DateTimeImmutable()]);
        $this->createConfiguredStub(ClockInterface::class, ['now' => new DateTimeImmutable()]);
    }

    public function testPinned(): void
    {
        $pinned = new DateTimeImmutable('2026-06-15T12:00:00+00:00');

        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-06-15T12:00:00+00:00'));
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-06-15 12:00:00', new DateTimeZone('UTC')));
        $clock->method('now')->willReturn($pinned);
        $clock->method('withTimeZone')->willReturn($clock);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('now')->willReturn(new DateTimeImmutable());
        $logger->method('now')->willReturnCallback(static fn () => new DateTimeImmutable());
        $this->createConfiguredMock(LoggerInterface::class, []);
    }
}
