<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Service;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Entity\TherapistSchedule;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Groups the data collections needed by AvailabilityComputer to reduce parameter count.
 */
final readonly class AvailabilityContext
{
    /**
     * @param ArrayCollection<int, TherapistSchedule> $schedules
     * @param ArrayCollection<int, ScheduleException> $exceptions
     * @param ArrayCollection<int, Appointment>       $blockingAppointments
     * @param ArrayCollection<int, SlotLock>           $activeLocks
     */
    public function __construct(
        public ArrayCollection $schedules,
        public ArrayCollection $exceptions,
        public ArrayCollection $blockingAppointments,
        public ArrayCollection $activeLocks,
    ) {
    }
}
