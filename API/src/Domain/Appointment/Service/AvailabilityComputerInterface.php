<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Service;

use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\ValueObject\TimeSlot;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

interface AvailabilityComputerInterface
{
    /**
     * Computes bookable slots in the half-open instant window [$from, $to).
     *
     * $from, $to and $now are absolute instants in any zone; every returned
     * TimeSlot carries UTC instants. Which schedule blocks apply on a given day
     * is decided in the practice zone from $slotGenerationRules, not in the
     * zone the window happens to be expressed in.
     *
     * @return ArrayCollection<int, TimeSlot>
     */
    public function computeAvailableSlots(
        AvailabilityContext $availabilityContext,
        SlotGenerationRules $slotGenerationRules,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        DateTimeImmutable $now,
        ?AppointmentModality $modalityFilter = null,
    ): ArrayCollection;
}
