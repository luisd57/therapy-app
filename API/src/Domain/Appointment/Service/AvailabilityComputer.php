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
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class AvailabilityComputer implements AvailabilityComputerInterface
{
    public function computeAvailableSlots(
        AvailabilityContext $availabilityContext,
        SlotGenerationRules $slotGenerationRules,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        DateTimeImmutable $now,
        ?AppointmentModality $modalityFilter = null,
    ): ArrayCollection {
        $slots = new ArrayCollection();

        if ($to <= $from) {
            return $slots;
        }

        $practiceTimeZone = $slotGenerationRules->practiceTimeZone;

        // Walk practice-local calendar days. Comparing formatted dates rather
        // than instants keeps the loop correct when the window boundaries fall
        // mid-day in the practice zone.
        $cursor = $from->setTimezone($practiceTimeZone)->setTime(0, 0);
        $lastDate = $to->setTimezone($practiceTimeZone)->format('Y-m-d');

        while ($cursor->format('Y-m-d') <= $lastDate) {
            // $cursor is practice-zoned, so this is the therapist's weekday,
            // not the weekday of the underlying UTC instant, which can differ.
            $weekDay = WeekDay::fromDateTimeImmutable($cursor);

            $daySchedules = $availabilityContext->schedules->filter(
                fn (TherapistSchedule $therapistSchedule) => $therapistSchedule->getDayOfWeek() === $weekDay
                    && $therapistSchedule->isActive()
                    && ($modalityFilter === null || $therapistSchedule->supportsModality($modalityFilter)),
            );

            foreach ($daySchedules as $therapistSchedule) {
                $this->collectBlockSlots(
                    $slots,
                    $availabilityContext,
                    $slotGenerationRules,
                    $cursor,
                    $therapistSchedule,
                    $from,
                    $to,
                    $now,
                );
            }

            $cursor = $cursor->modify('+1 day');
        }

        return $slots;
    }

    /**
     * @param ArrayCollection<int, TimeSlot> $slots
     */
    private function collectBlockSlots(
        ArrayCollection $slots,
        AvailabilityContext $availabilityContext,
        SlotGenerationRules $slotGenerationRules,
        DateTimeImmutable $practiceDay,
        TherapistSchedule $therapistSchedule,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        DateTimeImmutable $now,
    ): void {
        $blockStart = $this->materialize(
            $practiceDay,
            $therapistSchedule->getStartTime(),
            $slotGenerationRules->practiceTimeZone,
        );
        $blockEnd = $this->materialize(
            $practiceDay,
            $therapistSchedule->getEndTime(),
            $slotGenerationRules->practiceTimeZone,
        );

        if ($blockStart === null || $blockEnd === null || $blockEnd <= $blockStart) {
            return;
        }

        $slotStart = $blockStart;

        while (true) {
            $timeSlot = TimeSlot::create($slotStart, $slotGenerationRules->durationMinutes);

            // A session must fit entirely inside the block: the block end is the
            // time by which the therapist is finished, not the last start.
            if ($timeSlot->getEndTime() > $blockEnd) {
                return;
            }

            if ($slotStart >= $from
                && $slotStart < $to
                && !$this->isPast($timeSlot, $now)
                && !$this->isBlockedByException($timeSlot, $availabilityContext->exceptions)
                && !$this->isOccupiedByAppointment($timeSlot, $availabilityContext->blockingAppointments)
                && !$this->isHeldByLock($timeSlot, $availabilityContext->activeLocks, $now)
            ) {
                $slots->add($timeSlot);
            }

            // Stepping in UTC, so the increment is a physical duration. A practice zone with
            // DST would shift wall-clock starts after a transition. See ADR 0002.
            $slotStart = $slotStart->modify("+{$slotGenerationRules->startIncrementMinutes} minutes");
        }
    }

    /**
     * Turns a wall-clock rule ("08:00") into an instant by reading it in the practice zone.
     * The leading '!' zeroes unspecified fields, microseconds included, or slot equality misses.
     */
    private function materialize(
        DateTimeImmutable $practiceDay,
        string $wallClockTime,
        DateTimeZone $practiceTimeZone,
    ): ?DateTimeImmutable {
        $materialized = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $practiceDay->format('Y-m-d') . ' ' . $wallClockTime . ':00',
            $practiceTimeZone,
        );

        if ($materialized === false) {
            return null;
        }

        return $materialized->setTimezone(new DateTimeZone('UTC'));
    }

    private function isPast(TimeSlot $timeSlot, DateTimeImmutable $now): bool
    {
        return $timeSlot->getStartTime() <= $now;
    }

    /**
     * @param ArrayCollection<int, ScheduleException> $exceptions
     */
    private function isBlockedByException(TimeSlot $timeSlot, ArrayCollection $exceptions): bool
    {
        return $exceptions->exists(
            fn (int $_index, ScheduleException $scheduleException) => $scheduleException->overlapsTimeSlot($timeSlot),
        );
    }

    /**
     * @param ArrayCollection<int, Appointment> $appointments
     */
    private function isOccupiedByAppointment(TimeSlot $timeSlot, ArrayCollection $appointments): bool
    {
        return $appointments->exists(
            fn (int $_index, Appointment $appointment) => $appointment->blocksSlot()
                && $appointment->getTimeSlot()->overlaps($timeSlot),
        );
    }

    /**
     * @param ArrayCollection<int, SlotLock> $locks
     */
    private function isHeldByLock(TimeSlot $timeSlot, ArrayCollection $locks, DateTimeImmutable $now): bool
    {
        return $locks->exists(
            fn (int $_index, SlotLock $slotLock) => $slotLock->isActive($now)
                && $slotLock->getTimeSlot()->overlaps($timeSlot),
        );
    }
}
