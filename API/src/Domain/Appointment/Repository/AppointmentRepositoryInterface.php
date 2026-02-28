<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Repository;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Id\AppointmentId;
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

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findAll(): ArrayCollection;

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findAllPaginated(int $offset, int $limit): ArrayCollection;

    public function countAll(): int;

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findByStatusPaginated(AppointmentStatus $status, int $offset, int $limit): ArrayCollection;

    public function countByStatus(AppointmentStatus $status): int;

    /**
     * Returns only CONFIRMED appointments that overlap the given date range.
     * Used by AppointmentRequestService to allow multiple REQUESTED appointments for the same slot.
     *
     * @return ArrayCollection<int, Appointment>
     */
    public function findConfirmedByDateRange(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): ArrayCollection;

    /**
     * Returns confirmed appointments whose start_time falls on the given date, ordered by start_time ASC.
     *
     * @return ArrayCollection<int, Appointment>
     */
    public function findConfirmedByDate(DateTimeImmutable $date): ArrayCollection;

    public function delete(Appointment $appointment): void;
}
