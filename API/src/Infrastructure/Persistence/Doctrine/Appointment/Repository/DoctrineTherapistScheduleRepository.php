<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Appointment\Repository;

use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Enum\WeekDay;
use App\Domain\User\Id\UserId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineTherapistScheduleRepository implements TherapistScheduleRepositoryInterface
{
    /** @var EntityRepository<TherapistSchedule> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(TherapistSchedule::class);
    }

    public function save(TherapistSchedule $schedule): void
    {
        if (!$this->entityManager->contains($schedule)) {
            $this->entityManager->persist($schedule);
        }

        $this->entityManager->flush();
    }

    public function findById(ScheduleId $id): ?TherapistSchedule
    {
        return $this->entityManager->find(TherapistSchedule::class, $id->getValue());
    }

    /**
     * @return ArrayCollection<int, TherapistSchedule>
     */
    public function findActiveByTherapist(UserId $therapistId): ArrayCollection
    {
        return new ArrayCollection($this->repository->findBy([
            'therapistId' => $therapistId->getValue(),
            'isActive' => true,
        ]));
    }

    /**
     * @return ArrayCollection<int, TherapistSchedule>
     */
    public function findActiveByTherapistAndDay(UserId $therapistId, WeekDay $day): ArrayCollection
    {
        return new ArrayCollection($this->repository->findBy([
            'therapistId' => $therapistId->getValue(),
            'dayOfWeek' => $day->value,
            'isActive' => true,
        ]));
    }

    public function delete(TherapistSchedule $schedule): void
    {
        $managed = $this->entityManager->find(TherapistSchedule::class, $schedule->getId()->getValue());

        if ($managed !== null) {
            $this->entityManager->remove($managed);
            $this->entityManager->flush();
        }
    }
}
