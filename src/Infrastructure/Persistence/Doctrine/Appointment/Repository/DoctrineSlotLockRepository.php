<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Appointment\Repository;

use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Repository\SlotLockRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\Appointment\Entity\SlotLockEntity;
use App\Infrastructure\Persistence\Doctrine\Appointment\Mapper\SlotLockMapper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineSlotLockRepository implements SlotLockRepositoryInterface
{
    /** @var EntityRepository<SlotLockEntity> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(SlotLockEntity::class);
    }

    public function save(SlotLock $lock): void
    {
        $existingEntity = $this->repository->find($lock->getId()->getValue());

        $entity = SlotLockMapper::toEntity($lock, $existingEntity);

        if ($existingEntity === null) {
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    public function findActiveByTimeSlot(
        DateTimeImmutable $slotStart,
        DateTimeImmutable $slotEnd,
    ): ?SlotLock {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('l')
            ->from(SlotLockEntity::class, 'l')
            ->where('l.startTime < :end')
            ->andWhere('l.endTime > :start')
            ->andWhere('l.expiresAt > :now')
            ->setParameter('start', $slotStart)
            ->setParameter('end', $slotEnd)
            ->setParameter('now', new DateTimeImmutable())
            ->setMaxResults(1);

        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity !== null ? SlotLockMapper::toDomain($entity) : null;
    }

    public function findByLockToken(string $lockToken): ?SlotLock
    {
        $entity = $this->repository->findOneBy(['lockToken' => hash('sha256', $lockToken)]);

        return $entity !== null ? SlotLockMapper::toDomain($entity) : null;
    }

    public function delete(SlotLock $lock): void
    {
        $entity = $this->repository->find($lock->getId()->getValue());

        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    public function deleteExpired(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(SlotLockEntity::class, 'l')
            ->where('l.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable());

        return $qb->getQuery()->execute();
    }
}
