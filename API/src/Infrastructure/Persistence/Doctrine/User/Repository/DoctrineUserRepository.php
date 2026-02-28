<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\User\Repository;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineUserRepository implements UserRepositoryInterface
{
    /** @var EntityRepository<User> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(User::class);
    }

    public function save(User $user): void
    {
        if (!$this->entityManager->contains($user)) {
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();
    }

    public function findById(UserId $id): ?User
    {
        return $this->entityManager->find(User::class, $id->getValue());
    }

    public function findByEmail(Email $email): ?User
    {
        return $this->repository->findOneBy(['email' => $email->getValue()]);
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->repository->count(['email' => $email->getValue()]) > 0;
    }

    /**
     * @return ArrayCollection<int, User>
     */
    public function findByRole(UserRole $role): ArrayCollection
    {
        return new ArrayCollection($this->repository->findBy(['role' => $role->value]));
    }

    /**
     * @return ArrayCollection<int, User>
     */
    public function findActivePatients(): ArrayCollection
    {
        return new ArrayCollection($this->repository->findBy([
            'role' => UserRole::PATIENT->value,
            'isActive' => true,
        ]));
    }

    /**
     * @return ArrayCollection<int, User>
     */
    public function findActivePatientsPaginated(int $offset, int $limit): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('u')
            ->from(User::class, 'u')
            ->where('u.role = :role')
            ->andWhere('u.isActive = :isActive')
            ->setParameter('role', UserRole::PATIENT->value)
            ->setParameter('isActive', true)
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    public function countActivePatients(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.role = :role')
            ->andWhere('u.isActive = :isActive')
            ->setParameter('role', UserRole::PATIENT->value)
            ->setParameter('isActive', true);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findSingleTherapist(): User
    {
        $therapists = $this->findByRole(UserRole::THERAPIST);

        if ($therapists->count() === 0) {
            throw new \RuntimeException('No therapist found in the system.');
        }

        if ($therapists->count() > 1) {
            throw new \RuntimeException('Multiple therapists found. Expected exactly one.');
        }

        return $therapists->first();
    }

    public function delete(User $user): void
    {
        $managed = $this->entityManager->find(User::class, $user->getId()->getValue());

        if ($managed !== null) {
            $this->entityManager->remove($managed);
            $this->entityManager->flush();
        }
    }
}
