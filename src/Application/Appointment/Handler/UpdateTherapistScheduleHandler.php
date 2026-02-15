<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\UpdateTherapistScheduleInputDTO;
use App\Application\Appointment\DTO\Output\TherapistScheduleDTO;
use App\Domain\Appointment\Exception\ScheduleConflictException;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\ValueObject\ScheduleId;
use App\Domain\Appointment\ValueObject\WeekDay;
use App\Domain\User\ValueObject\UserId;

final readonly class UpdateTherapistScheduleHandler
{
    public function __construct(
        private TherapistScheduleRepositoryInterface $scheduleRepository,
    ) {
    }

    public function handle(UpdateTherapistScheduleInputDTO $input): TherapistScheduleDTO
    {
        $scheduleId = ScheduleId::fromString($input->scheduleId);
        $schedule = $this->scheduleRepository->findById($scheduleId);

        if ($schedule === null) {
            throw ScheduleConflictException::scheduleNotFound($input->scheduleId);
        }

        $dayOfWeek = WeekDay::from($input->dayOfWeek);

        // Check for overlaps, excluding self
        $existingSchedules = $this->scheduleRepository->findActiveByTherapistAndDay(
            UserId::fromString($input->therapistId),
            $dayOfWeek,
        );

        foreach ($existingSchedules as $existing) {
            if ($existing->getId()->equals($scheduleId)) {
                continue;
            }

            if ($this->timesOverlap(
                $input->startTime,
                $input->endTime,
                $existing->getStartTime(),
                $existing->getEndTime(),
            )) {
                throw ScheduleConflictException::overlap();
            }
        }

        $schedule->update(
            dayOfWeek: $dayOfWeek,
            startTime: $input->startTime,
            endTime: $input->endTime,
            supportsOnline: $input->supportsOnline,
            supportsInPerson: $input->supportsInPerson,
        );

        $this->scheduleRepository->save($schedule);

        return TherapistScheduleDTO::fromEntity($schedule);
    }

    private function timesOverlap(
        string $start1,
        string $end1,
        string $start2,
        string $end2,
    ): bool {
        return $start1 < $end2 && $start2 < $end1;
    }
}
