<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\SetTherapistScheduleInputDTO;
use App\Application\Appointment\DTO\Output\TherapistScheduleOutputDTO;
use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Exception\ScheduleConflictException;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\ValueObject\ScheduleId;
use App\Domain\Appointment\ValueObject\WeekDay;
use App\Domain\User\ValueObject\UserId;

final readonly class SetTherapistScheduleHandler
{
    public function __construct(
        private TherapistScheduleRepositoryInterface $scheduleRepository,
    ) {
    }

    public function __invoke(SetTherapistScheduleInputDTO $dto): TherapistScheduleOutputDTO
    {
        $therapistId = UserId::fromString($dto->therapistId);
        $dayOfWeek = WeekDay::from($dto->dayOfWeek);

        // Overlap check is time-based only, regardless of modality.
        // The therapist is one person and can only be in one place at a time.
        $existingSchedules = $this->scheduleRepository->findActiveByTherapistAndDay(
            $therapistId,
            $dayOfWeek,
        );

        foreach ($existingSchedules as $existing) {
            if ($this->timesOverlap(
                $dto->startTime,
                $dto->endTime,
                $existing->getStartTime(),
                $existing->getEndTime(),
            )) {
                throw ScheduleConflictException::overlap();
            }
        }

        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapistId: $therapistId,
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
