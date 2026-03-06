<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Service;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\Appointment\Enum\WeekDay;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class AvailabilityComputer implements AvailabilityComputerInterface
{
    public function computeAvailableSlots(
        AvailabilityContext $context,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $slotDurationMinutes,
        DateTimeImmutable $now,
        ?AppointmentModality $modalityFilter = null,
    ): ArrayCollection {
        $slots = new ArrayCollection();

        $current = $from;
        while ($current <= $to) {
            $weekDay = WeekDay::fromDateTimeImmutable($current);

            $daySchedules = $context->schedules->filter(
                fn (TherapistSchedule $schedule) => $schedule->getDayOfWeek() === $weekDay
                    && $schedule->isActive()
                    && ($modalityFilter === null || $schedule->supportsModality($modalityFilter)),
            );

            foreach ($daySchedules as $schedule) {
                $blockStart = new DateTimeImmutable(
                    $current->format('Y-m-d') . 'T' . $schedule->getStartTime() . ':00',
                );
                $blockEnd = new DateTimeImmutable(
                    $current->format('Y-m-d') . 'T' . $schedule->getEndTime() . ':00',
                );

                $slotStart = $blockStart;
                while ($slotStart->modify("+{$slotDurationMinutes} minutes") <= $blockEnd) {
                    $timeSlot = TimeSlot::create($slotStart, $slotDurationMinutes);

                    if (!$this->isPast($timeSlot, $now)
                        && !$this->isBlockedByException($timeSlot, $context->exceptions)
                        && !$this->isOccupiedByAppointment($timeSlot, $context->blockingAppointments)
                        && !$this->isHeldByLock($timeSlot, $context->activeLocks, $now)
                    ) {
                        $slots->add($timeSlot);
                    }

                    $slotStart = $slotStart->modify("+{$slotDurationMinutes} minutes");
                }
            }

            $current = $current->modify('+1 day');
        }

        return $slots;
    }

    private function isPast(TimeSlot $slot, DateTimeImmutable $now): bool
    {
        return $slot->getStartTime() <= $now;
    }

    /**
     * @param ArrayCollection<int, ScheduleException> $exceptions
     */
    private function isBlockedByException(TimeSlot $slot, ArrayCollection $exceptions): bool
    {
        return $exceptions->exists(
            fn (int $_index, ScheduleException $exception) => $exception->overlapsTimeSlot($slot),
        );
    }

    /**
     * @param ArrayCollection<int, Appointment> $appointments
     */
    private function isOccupiedByAppointment(TimeSlot $slot, ArrayCollection $appointments): bool
    {
        return $appointments->exists(
            fn (int $_index, Appointment $appointment) => $appointment->blocksSlot()
                && $appointment->getTimeSlot()->overlaps($slot),
        );
    }

    /**
     * @param ArrayCollection<int, SlotLock> $locks
     */
    private function isHeldByLock(TimeSlot $slot, ArrayCollection $locks, DateTimeImmutable $now): bool
    {
        return $locks->exists(
            fn (int $_index, SlotLock $slotLock) => $slotLock->isActive($now)
                && $slotLock->getTimeSlot()->overlaps($slot),
        );
    }
}
