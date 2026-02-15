<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\ValueObject\ScheduleId;
use App\Domain\Appointment\ValueObject\WeekDay;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Doctrine\Entity\TherapistScheduleEntity;
use App\Infrastructure\Persistence\Doctrine\Mapper\TherapistScheduleMapper;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineTherapistScheduleRepository implements TherapistScheduleRepositoryInterface
{
    /** @var EntityRepository<TherapistScheduleEntity> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(TherapistScheduleEntity::class);
    }

    public function save(TherapistSchedule $schedule): void
    {
        $existingEntity = $this->repository->find($schedule->getId()->getValue());

        $entity = TherapistScheduleMapper::toEntity($schedule, $existingEntity);

        if ($existingEntity === null) {
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    public function findById(ScheduleId $id): ?TherapistSchedule
    {
        $entity = $this->repository->find($id->getValue());

        return $entity !== null ? TherapistScheduleMapper::toDomain($entity) : null;
    }

    /**
     * @return ArrayCollection<int, TherapistSchedule>
     */
    public function findActiveByTherapist(UserId $therapistId): ArrayCollection
    {
        $entities = $this->repository->findBy([
            'therapistId' => $therapistId->getValue(),
            'isActive' => true,
        ]);

        $schedules = array_map(
            fn(TherapistScheduleEntity $entity) => TherapistScheduleMapper::toDomain($entity),
            $entities
        );

        return new ArrayCollection($schedules);
    }

    /**
     * @return ArrayCollection<int, TherapistSchedule>
     */
    public function findActiveByTherapistAndDay(UserId $therapistId, WeekDay $day): ArrayCollection
    {
        $entities = $this->repository->findBy([
            'therapistId' => $therapistId->getValue(),
            'dayOfWeek' => $day->value,
            'isActive' => true,
        ]);

        $schedules = array_map(
            fn(TherapistScheduleEntity $entity) => TherapistScheduleMapper::toDomain($entity),
            $entities
        );

        return new ArrayCollection($schedules);
    }

    public function delete(TherapistSchedule $schedule): void
    {
        $entity = $this->repository->find($schedule->getId()->getValue());

        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
