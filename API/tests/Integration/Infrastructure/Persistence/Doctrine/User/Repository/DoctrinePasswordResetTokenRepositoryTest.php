<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\User\Repository;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Id\UserId;
use App\Domain\User\Service\TokenGeneratorInterface;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;

final class DoctrinePasswordResetTokenRepositoryTest extends IntegrationTestCase
{
    private PasswordResetTokenRepositoryInterface $repository;
    private UserRepositoryInterface $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(PasswordResetTokenRepositoryInterface::class);
        $this->userRepository = self::getContainer()->get(UserRepositoryInterface::class);
    }

    private function persistUser(?UserId $id = null): User
    {
        $user = DomainTestHelper::createTherapist(
            id: $id ?? UserId::generate(),
            email: 'user-' . bin2hex(random_bytes(4)) . '@example.com',
        );
        $this->userRepository->save($user);
        return $user;
    }

    public function testSaveAndFindByToken(): void
    {
        $token = DomainTestHelper::createValidPasswordResetToken(token: 'save-reset-test', user: $this->persistUser());
        $this->repository->save($token);

        $this->entityManager->clear();

        $found = $this->repository->findByToken('save-reset-test');

        $this->assertNotNull($found);
        $this->assertSame(hash('sha256', 'save-reset-test'), $found->getToken());
    }

    public function testFindByTokenNonExistentReturnsNull(): void
    {
        $this->assertNull($this->repository->findByToken('nonexistent-reset'));
    }

    public function testFindValidByUserIdWithValidToken(): void
    {
        $user = $this->persistUser();
        $token = DomainTestHelper::createValidPasswordResetToken(
            token: 'valid-user-reset',
            user: $user,
        );
        $this->repository->save($token);

        $found = $this->repository->findValidByUserId($user->getId());

        $this->assertNotNull($found);
        $this->assertTrue($user->getId()->equals($found->getUserId()));
    }

    public function testFindValidByUserIdReturnsNullForExpiredOnly(): void
    {
        $user = $this->persistUser();
        $expired = DomainTestHelper::createExpiredPasswordResetToken(
            token: 'expired-user-reset',
            user: $user,
        );
        $this->repository->save($expired);

        $this->assertNull($this->repository->findValidByUserId($user->getId()));
    }

    public function testFindValidByUserIdReturnsNullForUsedOnly(): void
    {
        $user = $this->persistUser();
        $used = DomainTestHelper::createUsedPasswordResetToken(
            token: 'used-user-reset',
            user: $user,
        );
        $this->repository->save($used);

        $this->assertNull($this->repository->findValidByUserId($user->getId()));
    }

    public function testDeleteExpiredRemovesExpiredTokensOnly(): void
    {
        $user = $this->persistUser();
        $valid = DomainTestHelper::createValidPasswordResetToken(token: 'de-valid-reset', user: $user);
        $expired = DomainTestHelper::createExpiredPasswordResetToken(token: 'de-expired-reset', user: $user);

        $this->repository->save($valid);
        $this->repository->save($expired);

        $count = $this->repository->deleteExpired();

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertNotNull($this->repository->findByToken('de-valid-reset'));
        $this->assertNull($this->repository->findByToken('de-expired-reset'));
    }

    public function testInvalidateAllForUserMarksAllAsUsed(): void
    {
        $user = $this->persistUser();
        $token1 = DomainTestHelper::createValidPasswordResetToken(token: 'inv-1', user: $user);
        $token2 = DomainTestHelper::createValidPasswordResetToken(token: 'inv-2', user: $user);

        $this->repository->save($token1);
        $this->repository->save($token2);

        $this->repository->invalidateAllForUser($user->getId());

        // Clear the entity manager to get fresh data
        $this->entityManager->clear();

        $this->assertNull($this->repository->findValidByUserId($user->getId()));
    }

    public function testInvalidateAllForUserDoesNotAffectOtherUsers(): void
    {
        $user1 = $this->persistUser();
        $user2 = $this->persistUser();
        $token1 = DomainTestHelper::createValidPasswordResetToken(token: 'user1-reset', user: $user1);
        $token2 = DomainTestHelper::createValidPasswordResetToken(token: 'user2-reset', user: $user2);

        $this->repository->save($token1);
        $this->repository->save($token2);

        $this->repository->invalidateAllForUser($user1->getId());

        // Clear the entity manager to get fresh data
        $this->entityManager->clear();

        $this->assertNull($this->repository->findValidByUserId($user1->getId()));
        $this->assertNotNull($this->repository->findValidByUserId($user2->getId()));
    }

    public function testDeleteRemovesToken(): void
    {
        $token = DomainTestHelper::createValidPasswordResetToken(token: 'delete-reset', user: $this->persistUser());
        $this->repository->save($token);

        $this->repository->delete($token);

        $this->assertNull($this->repository->findById($token->getId()));
    }

    public function testTokenFromTheGeneratorIsStoredHashed(): void
    {
        $generator = self::getContainer()->get(TokenGeneratorInterface::class);
        $raw = $generator->generate();

        $token = DomainTestHelper::createValidPasswordResetToken(token: $raw, user: $this->persistUser());
        $this->repository->save($token);

        $this->entityManager->clear();

        $found = $this->repository->findByToken($raw);

        $this->assertNotNull($found);
        $this->assertNotSame($raw, $found->getToken());
        $this->assertSame(hash('sha256', $raw), $found->getToken());
    }

    public function testResavingAfterUseKeepsTheTokenFindable(): void
    {
        $generator = self::getContainer()->get(TokenGeneratorInterface::class);
        $raw = $generator->generate();

        $token = DomainTestHelper::createValidPasswordResetToken(token: $raw, user: $this->persistUser());
        $this->repository->save($token);

        $this->entityManager->clear();

        $reloaded = $this->repository->findByToken($raw);
        $this->assertNotNull($reloaded);
        $reloaded->use(new \DateTimeImmutable());
        $this->repository->save($reloaded);

        $this->entityManager->clear();

        $this->assertNotNull($this->repository->findByToken($raw));
    }
}
