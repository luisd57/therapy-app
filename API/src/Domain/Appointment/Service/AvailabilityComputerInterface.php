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
     * Computes available time slots for a given date range, optionally filtered by modality.
     *
     * @return ArrayCollection<int, TimeSlot>
     */
    public function computeAvailableSlots(
        AvailabilityContext $context,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $slotDurationMinutes,
        ?AppointmentModality $modalityFilter = null,
    ): ArrayCollection;
}
