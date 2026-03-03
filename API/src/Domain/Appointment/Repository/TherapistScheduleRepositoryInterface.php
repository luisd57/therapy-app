<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Repository;

use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Enum\WeekDay;
use App\Domain\User\Id\UserId;
use Doctrine\Common\Collections\ArrayCollection;

interface TherapistScheduleRepositoryInterface
{
    public function save(TherapistSchedule $schedule): void;

    public function findById(ScheduleId $id): ?TherapistSchedule;

    /**
     * @return ArrayCollection<int, TherapistSchedule>
     */
    public function findActiveByTherapist(UserId $therapistId): ArrayCollection;

    /**
     * @return ArrayCollection<int, TherapistSchedule>
     */
    public function findActiveByTherapistAndDay(UserId $therapistId, WeekDay $day): ArrayCollection;

    public function delete(TherapistSchedule $schedule): void;
}
