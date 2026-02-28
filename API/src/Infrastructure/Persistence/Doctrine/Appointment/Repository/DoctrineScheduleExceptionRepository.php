<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Appointment\Repository;

use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\Id\ExceptionId;
use App\Domain\User\Id\UserId;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineScheduleExceptionRepository implements ScheduleExceptionRepositoryInterface
{
    /** @var EntityRepository<ScheduleException> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(ScheduleException::class);
    }

    public function save(ScheduleException $exception): void
    {
        if (!$this->entityManager->contains($exception)) {
            $this->entityManager->persist($exception);
        }

        $this->entityManager->flush();
    }

    public function findById(ExceptionId $id): ?ScheduleException
    {
        return $this->entityManager->find(ScheduleException::class, $id->getValue());
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
            ->from(ScheduleException::class, 'e')
            ->where('e.therapistId = :therapistId')
            ->andWhere('e.startDateTime < :to')
            ->andWhere('e.endDateTime > :from')
            ->setParameter('therapistId', $therapistId->getValue())
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    public function delete(ScheduleException $exception): void
    {
        $managed = $this->entityManager->find(ScheduleException::class, $exception->getId()->getValue());

        if ($managed !== null) {
            $this->entityManager->remove($managed);
            $this->entityManager->flush();
        }
    }
}
