<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Output\TherapistScheduleOutputDTO;
use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class GetTherapistScheduleHandler
{
    public function __construct(
        private TherapistScheduleRepositoryInterface $scheduleRepository,
    ) {
    }

    /**
     * @return ArrayCollection<int, TherapistScheduleOutputDTO>
     */
    public function __invoke(string $therapistId): ArrayCollection
    {
        $schedules = $this->scheduleRepository->findActiveByTherapist(
            UserId::fromString($therapistId),
        );

        return new ArrayCollection(
            $schedules->map(
                fn (TherapistSchedule $schedule) => TherapistScheduleOutputDTO::fromEntity($schedule),
            )->toArray(),
        );
    }
}
