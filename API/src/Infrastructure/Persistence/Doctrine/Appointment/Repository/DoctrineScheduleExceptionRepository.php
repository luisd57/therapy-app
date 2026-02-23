<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Appointment\Repository;

use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\ValueObject\ExceptionId;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Doctrine\Appointment\Entity\ScheduleExceptionEntity;
use App\Infrastructure\Persistence\Doctrine\Appointment\Mapper\ScheduleExceptionMapper;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineScheduleExceptionRepository implements ScheduleExceptionRepositoryInterface
{
    /** @var EntityRepository<ScheduleExceptionEntity> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(ScheduleExceptionEntity::class);
    }

    public function save(ScheduleException $exception): void
    {
        $existingEntity = $this->repository->find($exception->getId()->getValue());

        $entity = ScheduleExceptionMapper::toEntity($exception, $existingEntity);

        if ($existingEntity === null) {
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    public function findById(ExceptionId $id): ?ScheduleException
    {
        $entity = $this->repository->find($id->getValue());

        return $entity !== null ? ScheduleExceptionMapper::toDomain($entity) : null;
    }

    /**
     * @return ArrayCollection<int, ScheduleException>
     */
    public function findByTherapistAndDateRange(
        UserId $therapistId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): ArrayCollection {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
            ->from(ScheduleExceptionEntity::class, 'e')
            ->where('e.therapistId = :therapistId')
            ->andWhere('e.startDateTime < :to')
            ->andWhere('e.endDateTime > :from')
            ->setParameter('therapistId', $therapistId->getValue())
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        $entities = $qb->getQuery()->getResult();

        $exceptions = array_map(
            fn(ScheduleExceptionEntity $entity) => ScheduleExceptionMapper::toDomain($entity),
            $entities
        );

        return new ArrayCollection($exceptions);
    }

    public function delete(ScheduleException $exception): void
    {
        $entity = $this->repository->find($exception->getId()->getValue());

        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
