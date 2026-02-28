<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\User\Repository;

use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\Id\TokenId;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineInvitationTokenRepository implements InvitationTokenRepositoryInterface
{
    /** @var EntityRepository<InvitationToken> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(InvitationToken::class);
    }

    public function save(InvitationToken $token): void
    {
        if (!$this->entityManager->contains($token)) {
            $this->entityManager->persist($token);
        }

        $this->entityManager->flush();
    }

    public function findById(TokenId $id): ?InvitationToken
    {
        return $this->entityManager->find(InvitationToken::class, $id->getValue());
    }

    public function findByToken(string $token): ?InvitationToken
    {
        return $this->repository->findOneBy(['token' => $token]);
    }

    public function findValidByEmail(Email $email): ?InvitationToken
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from(InvitationToken::class, 't')
            ->where('t.email = :email')
            ->andWhere('t.isUsed = false')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('email', $email->getValue())
            ->setParameter('now', new DateTimeImmutable())
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @return ArrayCollection<int, InvitationToken>
     */
    public function findPendingInvitations(): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from(InvitationToken::class, 't')
            ->where('t.isUsed = false')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('t.createdAt', 'DESC');

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    public function delete(InvitationToken $token): void
    {
        $managed = $this->entityManager->find(InvitationToken::class, $token->getId()->getValue());

        if ($managed !== null) {
            $this->entityManager->remove($managed);
            $this->entityManager->flush();
        }
    }

    public function deleteExpired(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(InvitationToken::class, 't')
            ->where('t.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable());

        return $qb->getQuery()->execute();
    }
}
