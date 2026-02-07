<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use App\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineUserRepository implements UserRepositoryInterface
{
    /** @var EntityRepository<UserEntity> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(UserEntity::class);
    }

    public function save(User $user): void
    {
        $existingEntity = $this->repository->find($user->getId()->getValue());

        $entity = UserMapper::toEntity($user, $existingEntity);

        if ($existingEntity === null) {
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    public function findById(UserId $id): ?User
    {
        $entity = $this->repository->find($id->getValue());

        return $entity !== null ? UserMapper::toDomain($entity) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $entity = $this->repository->findOneBy(['email' => $email->getValue()]);

        return $entity !== null ? UserMapper::toDomain($entity) : null;
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
        $entities = $this->repository->findBy(['role' => $role->value]);

        $users = array_map(
            fn(UserEntity $entity) => UserMapper::toDomain($entity),
            $entities
        );

        return new ArrayCollection($users);
    }

    /**
     * @return ArrayCollection<int, User>
     */
    public function findActivePatients(): ArrayCollection
    {
        $entities = $this->repository->findBy([
            'role' => UserRole::PATIENT->value,
            'isActive' => true,
        ]);

        $users = array_map(
            fn(UserEntity $entity) => UserMapper::toDomain($entity),
            $entities
        );

        return new ArrayCollection($users);
    }

    public function delete(User $user): void
    {
        $entity = $this->repository->find($user->getId()->getValue());

        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}