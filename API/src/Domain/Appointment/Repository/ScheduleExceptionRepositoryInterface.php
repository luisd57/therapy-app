<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Repository;

use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Id\ExceptionId;
use App\Domain\User\Id\UserId;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

interface ScheduleExceptionRepositoryInterface
{
    public function save(ScheduleException $exception): void;

    public function findById(ExceptionId $id): ?ScheduleException;

    /**
     * @return ArrayCollection<int, ScheduleException>
     */
    public function findByTherapistAndDateRange(
        UserId $therapistId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): ArrayCollection;

    public function delete(ScheduleException $exception): void;
}
