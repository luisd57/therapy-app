<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\User\Repository;

use App\Domain\User\Entity\PasswordResetToken;
use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Domain\User\ValueObject\TokenId;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Doctrine\User\Entity\PasswordResetTokenEntity;
use App\Infrastructure\Persistence\Doctrine\User\Mapper\PasswordResetTokenMapper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrinePasswordResetTokenRepository implements PasswordResetTokenRepositoryInterface
{
    /** @var EntityRepository<PasswordResetTokenEntity> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(PasswordResetTokenEntity::class);
    }

    public function save(PasswordResetToken $token): void
    {
        $existingEntity = $this->repository->find($token->getId()->getValue());

        $entity = PasswordResetTokenMapper::toEntity($token, $existingEntity);

        if ($existingEntity === null) {
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    public function findById(TokenId $id): ?PasswordResetToken
    {
        $entity = $this->repository->find($id->getValue());

        return $entity !== null ? PasswordResetTokenMapper::toDomain($entity) : null;
    }

    public function findByToken(string $token): ?PasswordResetToken
    {
        $entity = $this->repository->findOneBy(['token' => hash('sha256', $token)]);

        return $entity !== null ? PasswordResetTokenMapper::toDomain($entity) : null;
    }

    public function findValidByUserId(UserId $userId): ?PasswordResetToken
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from(PasswordResetTokenEntity::class, 't')
            ->where('t.userId = :userId')
            ->andWhere('t.isUsed = false')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('userId', $userId->getValue())
            ->setParameter('now', new DateTimeImmutable())
            ->setMaxResults(1);

        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity !== null ? PasswordResetTokenMapper::toDomain($entity) : null;
    }

    public function delete(PasswordResetToken $token): void
    {
        $entity = $this->repository->find($token->getId()->getValue());

        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    public function deleteExpired(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(PasswordResetTokenEntity::class, 't')
            ->where('t.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable());

        return $qb->getQuery()->execute();
    }

    public function invalidateAllForUser(UserId $userId): void
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update(PasswordResetTokenEntity::class, 't')
            ->set('t.isUsed', 'true')
            ->set('t.usedAt', ':now')
            ->where('t.userId = :userId')
            ->andWhere('t.isUsed = false')
            ->setParameter('userId', $userId->getValue())
            ->setParameter('now', new DateTimeImmutable());

        $qb->getQuery()->execute();
    }
}