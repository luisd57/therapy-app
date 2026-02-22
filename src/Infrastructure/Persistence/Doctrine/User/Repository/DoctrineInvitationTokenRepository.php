<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\User\Repository;

use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\TokenId;
use App\Infrastructure\Persistence\Doctrine\User\Entity\InvitationTokenEntity;
use App\Infrastructure\Persistence\Doctrine\User\Mapper\InvitationTokenMapper;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineInvitationTokenRepository implements InvitationTokenRepositoryInterface
{
    /** @var EntityRepository<InvitationTokenEntity> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(InvitationTokenEntity::class);
    }

    public function save(InvitationToken $token): void
    {
        $existingEntity = $this->repository->find($token->getId()->getValue());

        $entity = InvitationTokenMapper::toEntity($token, $existingEntity);

        if ($existingEntity === null) {
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    public function findById(TokenId $id): ?InvitationToken
    {
        $entity = $this->repository->find($id->getValue());

        return $entity !== null ? InvitationTokenMapper::toDomain($entity) : null;
    }

    public function findByToken(string $token): ?InvitationToken
    {
        $entity = $this->repository->findOneBy(['token' => hash('sha256', $token)]);

        return $entity !== null ? InvitationTokenMapper::toDomain($entity) : null;
    }

    public function findValidByEmail(Email $email): ?InvitationToken
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from(InvitationTokenEntity::class, 't')
            ->where('t.email = :email')
            ->andWhere('t.isUsed = false')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('email', $email->getValue())
            ->setParameter('now', new DateTimeImmutable())
            ->setMaxResults(1);

        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity !== null ? InvitationTokenMapper::toDomain($entity) : null;
    }

    /**
     * @return ArrayCollection<int, InvitationToken>
     */
    public function findPendingInvitations(): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from(InvitationTokenEntity::class, 't')
            ->where('t.isUsed = false')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('t.createdAt', 'DESC');

        $entities = $qb->getQuery()->getResult();

        $tokens = array_map(
            fn(InvitationTokenEntity $entity) => InvitationTokenMapper::toDomain($entity),
            $entities
        );

        return new ArrayCollection($tokens);
    }

    public function delete(InvitationToken $token): void
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
        $qb->delete(InvitationTokenEntity::class, 't')
            ->where('t.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable());

        return $qb->getQuery()->execute();
    }
}