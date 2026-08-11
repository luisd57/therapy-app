<?php

declare(strict_types=1);

namespace App\Application\Shared;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Renders instants for API responses: always UTC, always with an explicit offset.
 * Never the server's zone, or the response starts depending on deployment config. See ADR 0001.
 */
final readonly class InstantFormatter
{
    public static function toAtomUtc(?DateTimeImmutable $instant): ?string
    {
        return $instant?->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
    }
}
