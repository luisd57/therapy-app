<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\DeleteTherapistScheduleInputDTO;
use App\Domain\Appointment\Exception\ScheduleConflictException;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Id\ScheduleId;

final readonly class DeleteTherapistScheduleHandler
{
    public function __construct(
        private TherapistScheduleRepositoryInterface $scheduleRepository,
    ) {
    }

    public function __invoke(DeleteTherapistScheduleInputDTO $dto): void
    {
        $id = ScheduleId::fromString($dto->scheduleId);
        $schedule = $this->scheduleRepository->findById($id);

        if ($schedule === null) {
            throw ScheduleConflictException::scheduleNotFound($dto->scheduleId);
        }

        $this->scheduleRepository->delete($schedule);
    }
}
