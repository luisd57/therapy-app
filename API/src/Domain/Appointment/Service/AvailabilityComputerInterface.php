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
     * Which blocks apply on a day is decided in the practice zone, not the window's zone.
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
