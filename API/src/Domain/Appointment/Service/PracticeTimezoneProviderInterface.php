<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Service;

use DateTimeZone;

/**
 * The zone the therapist's schedule blocks are expressed in.
 *
 * A block of "08:00-12:00 on Monday" is a recurring wall-clock rule, not an
 * instant; it only becomes a point on the timeline once read against this zone.
 * Single-therapist practice, so there is exactly one.
 */
interface PracticeTimezoneProviderInterface
{
    public function getTimeZone(): DateTimeZone;

    /**
     * The IANA identifier, for responses that tell a client which zone the
     * practice keeps so it never has to hardcode one.
     */
    public function getIdentifier(): string;
}
