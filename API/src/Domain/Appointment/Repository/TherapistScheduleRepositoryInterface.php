<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Repository;

use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\ValueObject\ScheduleId;
use App\Domain\Appointment\ValueObject\WeekDay;
use App\Domain\User\ValueObject\UserId;
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
