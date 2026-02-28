<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Appointment\Repository;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\ValueObject\AppointmentId;
use App\Domain\Appointment\ValueObject\AppointmentStatus;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineAppointmentRepository implements AppointmentRepositoryInterface
{
    /** @var EntityRepository<Appointment> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(Appointment::class);
    }

    public function save(Appointment $appointment): void
    {
        if (!$this->entityManager->contains($appointment)) {
            $this->entityManager->persist($appointment);
        }

        $this->entityManager->flush();
    }

    public function findById(AppointmentId $id): ?Appointment
    {
        return $this->entityManager->find(Appointment::class, $id->getValue());
    }

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findBlockingByDateRange(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): ArrayCollection {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(Appointment::class, 'a')
            ->where('a.status IN (:statuses)')
            ->andWhere('a.timeSlot.startTime < :to')
            ->andWhere('a.timeSlot.endTime > :from')
            ->setParameter('statuses', [
                AppointmentStatus::REQUESTED->value,
                AppointmentStatus::CONFIRMED->value,
            ])
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findByStatus(AppointmentStatus $status): ArrayCollection
    {
        return new ArrayCollection($this->repository->findBy(['status' => $status->value]));
    }

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findAll(): ArrayCollection
    {
        return new ArrayCollection($this->repository->findBy([], ['createdAt' => 'DESC']));
    }

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findAllPaginated(int $offset, int $limit): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(Appointment::class, 'a')
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    public function countAll(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(a.id)')
            ->from(Appointment::class, 'a');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findByStatusPaginated(AppointmentStatus $status, int $offset, int $limit): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(Appointment::class, 'a')
            ->where('a.status = :status')
            ->setParameter('status', $status->value)
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    public function countByStatus(AppointmentStatus $status): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(a.id)')
            ->from(Appointment::class, 'a')
            ->where('a.status = :status')
            ->setParameter('status', $status->value);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findConfirmedByDateRange(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): ArrayCollection {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(Appointment::class, 'a')
            ->where('a.status = :status')
            ->andWhere('a.timeSlot.startTime < :to')
            ->andWhere('a.timeSlot.endTime > :from')
            ->setParameter('status', AppointmentStatus::CONFIRMED->value)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findConfirmedByDate(DateTimeImmutable $date): ArrayCollection
    {
        $dayStart = $date->setTime(0, 0);
        $dayEnd = $date->modify('+1 day')->setTime(0, 0);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(Appointment::class, 'a')
            ->where('a.status = :status')
            ->andWhere('a.timeSlot.startTime >= :dayStart')
            ->andWhere('a.timeSlot.startTime < :dayEnd')
            ->setParameter('status', AppointmentStatus::CONFIRMED->value)
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->orderBy('a.timeSlot.startTime', 'ASC');

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    public function delete(Appointment $appointment): void
    {
        $managed = $this->entityManager->find(Appointment::class, $appointment->getId()->getValue());

        if ($managed !== null) {
            $this->entityManager->remove($managed);
            $this->entityManager->flush();
        }
    }
}
