<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Repository;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\ValueObject\AppointmentId;
use App\Domain\Appointment\ValueObject\AppointmentStatus;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

interface AppointmentRepositoryInterface
{
    public function save(Appointment $appointment): void;

    public function findById(AppointmentId $id): ?Appointment;

    /**
     * Returns appointments with status REQUESTED or CONFIRMED that overlap the given date range.
     *
     * @return ArrayCollection<int, Appointment>
     */
    public function findBlockingByDateRange(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): ArrayCollection;

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findByStatus(AppointmentStatus $status): ArrayCollection;

    public function delete(Appointment $appointment): void;
}
