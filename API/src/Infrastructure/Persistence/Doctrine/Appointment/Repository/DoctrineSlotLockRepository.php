<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Appointment\Repository;

use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Repository\SlotLockRepositoryInterface;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineSlotLockRepository implements SlotLockRepositoryInterface
{
    /** @var EntityRepository<SlotLock> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(SlotLock::class);
    }

    public function save(SlotLock $lock): void
    {
        if (!$this->entityManager->contains($lock)) {
            $this->entityManager->persist($lock);
        }

        $this->entityManager->flush();
    }

    /**
     * @return ArrayCollection<int, SlotLock>
     */
    public function findActiveByDateRange(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): ArrayCollection {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('l')
            ->from(SlotLock::class, 'l')
            ->where('l.timeSlot.startTime < :to')
            ->andWhere('l.timeSlot.endTime > :from')
            ->andWhere('l.expiresAt > :now')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('now', new DateTimeImmutable());

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    public function findActiveByTimeSlot(
        DateTimeImmutable $slotStart,
        DateTimeImmutable $slotEnd,
    ): ?SlotLock {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('l')
            ->from(SlotLock::class, 'l')
            ->where('l.timeSlot.startTime < :end')
            ->andWhere('l.timeSlot.endTime > :start')
            ->andWhere('l.expiresAt > :now')
            ->setParameter('start', $slotStart)
            ->setParameter('end', $slotEnd)
            ->setParameter('now', new DateTimeImmutable())
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findByLockToken(string $lockToken): ?SlotLock
    {
        return $this->repository->findOneBy(['lockToken' => $lockToken]);
    }

    public function delete(SlotLock $lock): void
    {
        $managed = $this->entityManager->find(SlotLock::class, $lock->getId()->getValue());

        if ($managed !== null) {
            $this->entityManager->remove($managed);
            $this->entityManager->flush();
        }
    }

    public function deleteExpired(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(SlotLock::class, 'l')
            ->where('l.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable());

        return $qb->getQuery()->execute();
    }
}
