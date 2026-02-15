<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\DeleteTherapistScheduleInputDTO;
use App\Domain\Appointment\Exception\ScheduleConflictException;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\ValueObject\ScheduleId;

final readonly class DeleteTherapistScheduleHandler
{
    public function __construct(
        private TherapistScheduleRepositoryInterface $scheduleRepository,
    ) {
    }

    public function handle(DeleteTherapistScheduleInputDTO $input): void
    {
        $id = ScheduleId::fromString($input->scheduleId);
        $schedule = $this->scheduleRepository->findById($id);

        if ($schedule === null) {
            throw ScheduleConflictException::scheduleNotFound($input->scheduleId);
        }

        $this->scheduleRepository->delete($schedule);
    }
}
