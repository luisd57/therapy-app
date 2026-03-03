<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\UpdateTherapistScheduleInputDTO;
use App\Application\Appointment\DTO\Output\TherapistScheduleOutputDTO;
use App\Domain\Appointment\Exception\ScheduleConflictException;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Enum\WeekDay;
use App\Domain\User\Id\UserId;

final readonly class UpdateTherapistScheduleHandler
{
    public function __construct(
        private TherapistScheduleRepositoryInterface $scheduleRepository,
    ) {
    }

    public function __invoke(UpdateTherapistScheduleInputDTO $dto): TherapistScheduleOutputDTO
    {
        $scheduleId = ScheduleId::fromString($dto->scheduleId);
        $schedule = $this->scheduleRepository->findById($scheduleId);

        if ($schedule === null) {
            throw ScheduleConflictException::scheduleNotFound($dto->scheduleId);
        }

        $dayOfWeek = WeekDay::from($dto->dayOfWeek);

        // Check for overlaps, excluding self
        $existingSchedules = $this->scheduleRepository->findActiveByTherapistAndDay(
            UserId::fromString($dto->therapistId),
            $dayOfWeek,
        );

        foreach ($existingSchedules as $existing) {
            if ($existing->getId()->equals($scheduleId)) {
                continue;
            }

            if ($this->timesOverlap(
                $dto->startTime,
                $dto->endTime,
                $existing->getStartTime(),
                $existing->getEndTime(),
            )) {
                throw ScheduleConflictException::overlap();
            }
        }

        $schedule->update(
            dayOfWeek: $dayOfWeek,
            startTime: $dto->startTime,
            endTime: $dto->endTime,
            supportsOnline: $dto->supportsOnline,
            supportsInPerson: $dto->supportsInPerson,
        );

        $this->scheduleRepository->save($schedule);

        return TherapistScheduleOutputDTO::fromEntity($schedule);
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
