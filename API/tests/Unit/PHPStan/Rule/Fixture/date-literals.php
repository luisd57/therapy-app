<?php

declare(strict_types=1);

namespace App\Tests\Unit\PHPStan\Rule\Fixture;

use DateTimeImmutable;
use DateTimeZone;

const NAIVE = '2026-09-15 09:00:00';

function naive(): void
{
    new DateTimeImmutable('2026-06-02 09:00:00');
    new \DateTimeImmutable('2026-03-09');
    new DateTimeImmutable('2026-04-01 09:00');
    new DateTimeImmutable('tomorrow 09:00:00');
    new DateTimeImmutable('tomorrow');
    new DateTimeImmutable('noon');
    new DateTimeImmutable(NAIVE);
    $inlined = '2026-06-02T09:00:00';
    new DateTimeImmutable($inlined);
}

function zoned(string $fromOutside): void
{
    new DateTimeImmutable('2026-06-01T09:00:00-04:00');
    new DateTimeImmutable('2026-06-01T09:00:00Z');
    new DateTimeImmutable('2026-06-01 09:00 America/Caracas');
    new DateTimeImmutable('@1700000000');
    new DateTimeImmutable('2026-06-02 09:00:00', new DateTimeZone('UTC'));
    new DateTimeImmutable('-1 hour');
    new DateTimeImmutable('+1 day');
    new DateTimeImmutable('now');
    new DateTimeImmutable();
    new DateTimeImmutable($fromOutside);
    new DateTimeZone('2026-06-02 09:00:00');
}
