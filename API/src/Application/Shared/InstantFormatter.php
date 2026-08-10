<?php

declare(strict_types=1);

namespace App\Application\Shared;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Renders instants for API responses.
 *
 * Always UTC, always with an explicit offset. Clients convert to whatever zone
 * their reader is in; the API never guesses on their behalf. Formatting in the
 * server's zone would make the response depend on deployment config, which is
 * how a '-04:00' offset that was never stored ended up in payloads before.
 */
final readonly class InstantFormatter
{
    public static function toAtomUtc(?DateTimeImmutable $instant): ?string
    {
        return $instant?->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
    }
}
