<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Appointment\Repository;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\ValueObject\AppointmentId;
use App\Domain\Appointment\ValueObject\AppointmentStatus;
use App\Infrastructure\Persistence\Doctrine\Appointment\Entity\AppointmentEntity;
use App\Infrastructure\Persistence\Doctrine\Appointment\Mapper\AppointmentMapper;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineAppointmentRepository implements AppointmentRepositoryInterface
{
    /** @var EntityRepository<AppointmentEntity> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(AppointmentEntity::class);
    }

    public function save(Appointment $appointment): void
    {
        $existingEntity = $this->repository->find($appointment->getId()->getValue());

        $entity = AppointmentMapper::toEntity($appointment, $existingEntity);

        if ($existingEntity === null) {
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    public function findById(AppointmentId $id): ?Appointment
    {
        $entity = $this->repository->find($id->getValue());

        return $entity !== null ? AppointmentMapper::toDomain($entity) : null;
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
            ->from(AppointmentEntity::class, 'a')
            ->where('a.status IN (:statuses)')
            ->andWhere('a.startTime < :to')
            ->andWhere('a.endTime > :from')
            ->setParameter('statuses', [
                AppointmentStatus::REQUESTED->value,
                AppointmentStatus::CONFIRMED->value,
            ])
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        $entities = $qb->getQuery()->getResult();

        $appointments = array_map(
            fn(AppointmentEntity $entity) => AppointmentMapper::toDomain($entity),
            $entities
        );

        return new ArrayCollection($appointments);
    }

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findByStatus(AppointmentStatus $status): ArrayCollection
    {
        $entities = $this->repository->findBy(['status' => $status->value]);

        $appointments = array_map(
            fn(AppointmentEntity $entity) => AppointmentMapper::toDomain($entity),
            $entities
        );

        return new ArrayCollection($appointments);
    }

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findAll(): ArrayCollection
    {
        $entities = $this->repository->findBy([], ['createdAt' => 'DESC']);

        $appointments = array_map(
            fn(AppointmentEntity $entity) => AppointmentMapper::toDomain($entity),
            $entities
        );

        return new ArrayCollection($appointments);
    }

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findAllPaginated(int $offset, int $limit): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(AppointmentEntity::class, 'a')
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $entities = $qb->getQuery()->getResult();

        $appointments = array_map(
            fn(AppointmentEntity $entity) => AppointmentMapper::toDomain($entity),
            $entities,
        );

        return new ArrayCollection($appointments);
    }

    public function countAll(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(a.id)')
            ->from(AppointmentEntity::class, 'a');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return ArrayCollection<int, Appointment>
     */
    public function findByStatusPaginated(AppointmentStatus $status, int $offset, int $limit): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(AppointmentEntity::class, 'a')
            ->where('a.status = :status')
            ->setParameter('status', $status->value)
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $entities = $qb->getQuery()->getResult();

        $appointments = array_map(
            fn(AppointmentEntity $entity) => AppointmentMapper::toDomain($entity),
            $entities,
        );

        return new ArrayCollection($appointments);
    }

    public function countByStatus(AppointmentStatus $status): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(a.id)')
            ->from(AppointmentEntity::class, 'a')
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
            ->from(AppointmentEntity::class, 'a')
            ->where('a.status = :status')
            ->andWhere('a.startTime < :to')
            ->andWhere('a.endTime > :from')
            ->setParameter('status', AppointmentStatus::CONFIRMED->value)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        $entities = $qb->getQuery()->getResult();

        $appointments = array_map(
            fn(AppointmentEntity $entity) => AppointmentMapper::toDomain($entity),
            $entities,
        );

        return new ArrayCollection($appointments);
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
            ->from(AppointmentEntity::class, 'a')
            ->where('a.status = :status')
            ->andWhere('a.startTime >= :dayStart')
            ->andWhere('a.startTime < :dayEnd')
            ->setParameter('status', AppointmentStatus::CONFIRMED->value)
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->orderBy('a.startTime', 'ASC');

        $entities = $qb->getQuery()->getResult();

        $appointments = array_map(
            fn(AppointmentEntity $entity) => AppointmentMapper::toDomain($entity),
            $entities,
        );

        return new ArrayCollection($appointments);
    }

    public function delete(Appointment $appointment): void
    {
        $entity = $this->repository->find($appointment->getId()->getValue());

        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
