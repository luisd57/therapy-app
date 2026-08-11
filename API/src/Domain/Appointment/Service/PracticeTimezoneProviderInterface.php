<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Service;

use DateTimeZone;

/**
 * The zone the therapist's schedule blocks are expressed in.
 * A block is a wall-clock rule, not an instant; it only lands on the timeline once read here.
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
