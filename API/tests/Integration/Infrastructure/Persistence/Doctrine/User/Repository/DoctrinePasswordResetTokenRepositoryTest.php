<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\User\Repository;

use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Id\UserId;
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

    private function persistUser(?UserId $id = null): UserId
    {
        $userId = $id ?? UserId::generate();
        $user = DomainTestHelper::createTherapist(
            id: $userId,
            email: 'user-' . bin2hex(random_bytes(4)) . '@example.com',
        );
        $this->userRepository->save($user);
        return $userId;
    }

    public function testSaveAndFindByToken(): void
    {
        $token = DomainTestHelper::createValidPasswordResetToken(token: 'save-reset-test', userId: $this->persistUser());
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
        $userId = $this->persistUser();
        $token = DomainTestHelper::createValidPasswordResetToken(
            token: 'valid-user-reset',
            userId: $userId,
        );
        $this->repository->save($token);

        $found = $this->repository->findValidByUserId($userId);

        $this->assertNotNull($found);
        $this->assertTrue($userId->equals($found->getUserId()));
    }

    public function testFindValidByUserIdReturnsNullForExpiredOnly(): void
    {
        $userId = $this->persistUser();
        $expired = DomainTestHelper::createExpiredPasswordResetToken(
            token: 'expired-user-reset',
            userId: $userId,
        );
        $this->repository->save($expired);

        $this->assertNull($this->repository->findValidByUserId($userId));
    }

    public function testFindValidByUserIdReturnsNullForUsedOnly(): void
    {
        $userId = $this->persistUser();
        $used = DomainTestHelper::createUsedPasswordResetToken(
            token: 'used-user-reset',
            userId: $userId,
        );
        $this->repository->save($used);

        $this->assertNull($this->repository->findValidByUserId($userId));
    }

    public function testDeleteExpiredRemovesExpiredTokensOnly(): void
    {
        $userId = $this->persistUser();
        $valid = DomainTestHelper::createValidPasswordResetToken(token: 'de-valid-reset', userId: $userId);
        $expired = DomainTestHelper::createExpiredPasswordResetToken(token: 'de-expired-reset', userId: $userId);

        $this->repository->save($valid);
        $this->repository->save($expired);

        $count = $this->repository->deleteExpired();

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertNotNull($this->repository->findByToken('de-valid-reset'));
        $this->assertNull($this->repository->findByToken('de-expired-reset'));
    }

    public function testInvalidateAllForUserMarksAllAsUsed(): void
    {
        $userId = $this->persistUser();
        $token1 = DomainTestHelper::createValidPasswordResetToken(token: 'inv-1', userId: $userId);
        $token2 = DomainTestHelper::createValidPasswordResetToken(token: 'inv-2', userId: $userId);

        $this->repository->save($token1);
        $this->repository->save($token2);

        $this->repository->invalidateAllForUser($userId);

        // Clear the entity manager to get fresh data
        $this->entityManager->clear();

        $this->assertNull($this->repository->findValidByUserId($userId));
    }

    public function testInvalidateAllForUserDoesNotAffectOtherUsers(): void
    {
        $userId1 = $this->persistUser();
        $userId2 = $this->persistUser();
        $token1 = DomainTestHelper::createValidPasswordResetToken(token: 'user1-reset', userId: $userId1);
        $token2 = DomainTestHelper::createValidPasswordResetToken(token: 'user2-reset', userId: $userId2);

        $this->repository->save($token1);
        $this->repository->save($token2);

        $this->repository->invalidateAllForUser($userId1);

        // Clear the entity manager to get fresh data
        $this->entityManager->clear();

        $this->assertNull($this->repository->findValidByUserId($userId1));
        $this->assertNotNull($this->repository->findValidByUserId($userId2));
    }

    public function testDeleteRemovesToken(): void
    {
        $token = DomainTestHelper::createValidPasswordResetToken(token: 'delete-reset', userId: $this->persistUser());
        $this->repository->save($token);

        $this->repository->delete($token);

        $this->assertNull($this->repository->findById($token->getId()));
    }
}
