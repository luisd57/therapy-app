<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Service;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\Appointment\ValueObject\WeekDay;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class AvailabilityComputer implements AvailabilityComputerInterface
{
    public function computeAvailableSlots(
        AvailabilityContext $context,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $slotDurationMinutes,
        ?AppointmentModality $modalityFilter = null,
    ): ArrayCollection {
        $slots = new ArrayCollection();
        $now = new DateTimeImmutable();

        $current = $from;
        while ($current <= $to) {
            $weekDay = WeekDay::fromDateTimeImmutable($current);

            $daySchedules = $context->schedules->filter(
                fn (TherapistSchedule $s) => $s->getDayOfWeek() === $weekDay
                    && $s->isActive()
                    && ($modalityFilter === null || $s->supportsModality($modalityFilter)),
            );

            foreach ($daySchedules as $schedule) {
                $blockStart = DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i',
                    $current->format('Y-m-d') . ' ' . $schedule->getStartTime(),
                );
                $blockEnd = DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i',
                    $current->format('Y-m-d') . ' ' . $schedule->getEndTime(),
                );

                if ($blockStart === false || $blockEnd === false) {
                    continue;
                }

                $slotStart = $blockStart;
                while ($slotStart->modify("+{$slotDurationMinutes} minutes") <= $blockEnd) {
                    $timeSlot = TimeSlot::create($slotStart, $slotDurationMinutes);

                    if (!$this->isPast($timeSlot, $now)
                        && !$this->isBlockedByException($timeSlot, $context->exceptions)
                        && !$this->isOccupiedByAppointment($timeSlot, $context->blockingAppointments)
                        && !$this->isHeldByLock($timeSlot, $context->activeLocks)
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
            fn (int $_, ScheduleException $ex) => $ex->overlapsTimeSlot($slot),
        );
    }

    /**
     * @param ArrayCollection<int, Appointment> $appointments
     */
    private function isOccupiedByAppointment(TimeSlot $slot, ArrayCollection $appointments): bool
    {
        return $appointments->exists(
            fn (int $_, Appointment $appt) => $appt->blocksSlot()
                && $appt->getTimeSlot()->overlaps($slot),
        );
    }

    /**
     * @param ArrayCollection<int, SlotLock> $locks
     */
    private function isHeldByLock(TimeSlot $slot, ArrayCollection $locks): bool
    {
        return $locks->exists(
            fn (int $_, SlotLock $lock) => $lock->isActive()
                && $lock->getTimeSlot()->overlaps($slot),
        );
    }
}
