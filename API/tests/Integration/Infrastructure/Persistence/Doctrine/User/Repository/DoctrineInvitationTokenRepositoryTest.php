<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\User\Repository;

use App\Domain\User\Id\UserId;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;

final class DoctrineInvitationTokenRepositoryTest extends IntegrationTestCase
{
    private InvitationTokenRepositoryInterface $repository;
    private UserId $therapistId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(InvitationTokenRepositoryInterface::class);

        $therapist = DomainTestHelper::createTherapist(email: 'inviter-' . bin2hex(random_bytes(4)) . '@example.com');
        $this->therapistId = $therapist->getId();
        self::getContainer()->get(UserRepositoryInterface::class)->save($therapist);
    }

    public function testSaveAndFindById(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(token: 'save-test-token', invitedBy: $this->therapistId);
        $this->repository->save($invitation);

        $this->entityManager->clear();

        $found = $this->repository->findById($invitation->getId());

        $this->assertNotNull($found);
        $this->assertSame(hash('sha256', 'save-test-token'), $found->getToken());
    }

    public function testFindByTokenExistingReturnsDomainEntity(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(token: 'find-by-token', invitedBy: $this->therapistId);
        $this->repository->save($invitation);

        $found = $this->repository->findByToken('find-by-token');

        $this->assertNotNull($found);
        $this->assertTrue($invitation->getId()->equals($found->getId()));
    }

    public function testFindByTokenNonExistentReturnsNull(): void
    {
        $this->assertNull($this->repository->findByToken('nonexistent-token'));
    }

    public function testFindValidByEmailWithValidToken(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(
            token: 'valid-email-token',
            email: 'valid@example.com',
            invitedBy: $this->therapistId,
        );
        $this->repository->save($invitation);

        $found = $this->repository->findValidByEmail(Email::fromString('valid@example.com'));

        $this->assertNotNull($found);
        $this->assertSame('valid@example.com', $found->getEmail()->getValue());
    }

    public function testFindValidByEmailReturnsNullForExpiredOnly(): void
    {
        $expired = DomainTestHelper::createExpiredInvitation(
            token: 'expired-only-token',
            email: 'expired-only@example.com',
            invitedBy: $this->therapistId,
        );
        $this->repository->save($expired);

        $found = $this->repository->findValidByEmail(Email::fromString('expired-only@example.com'));
        $this->assertNull($found);
    }

    public function testFindValidByEmailReturnsNullForUsedOnly(): void
    {
        $used = DomainTestHelper::createUsedInvitation(
            token: 'used-only-token',
            email: 'used-only@example.com',
            invitedBy: $this->therapistId,
        );
        $this->repository->save($used);

        $found = $this->repository->findValidByEmail(Email::fromString('used-only@example.com'));
        $this->assertNull($found);
    }

    public function testFindPendingInvitationsReturnsOnlyValidTokens(): void
    {
        $valid = DomainTestHelper::createValidInvitation(token: 'pending-valid', email: 'pv@example.com', invitedBy: $this->therapistId);
        $expired = DomainTestHelper::createExpiredInvitation(token: 'pending-expired', email: 'pe@example.com', invitedBy: $this->therapistId);
        $used = DomainTestHelper::createUsedInvitation(token: 'pending-used', email: 'pu@example.com', invitedBy: $this->therapistId);

        $this->repository->save($valid);
        $this->repository->save($expired);
        $this->repository->save($used);

        $pending = $this->repository->findPendingInvitations();

        $emails = $pending->map(fn($inv) => $inv->getEmail()->getValue())->toArray();
        $this->assertContains('pv@example.com', $emails);
        $this->assertNotContains('pe@example.com', $emails);
        $this->assertNotContains('pu@example.com', $emails);
    }

    public function testDeleteExpiredRemovesExpiredAndReturnsCount(): void
    {
        $valid = DomainTestHelper::createValidInvitation(token: 'de-valid', email: 'de-v@example.com', invitedBy: $this->therapistId);
        $expired = DomainTestHelper::createExpiredInvitation(token: 'de-expired', email: 'de-e@example.com', invitedBy: $this->therapistId);

        $this->repository->save($valid);
        $this->repository->save($expired);

        $count = $this->repository->deleteExpired();

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertNotNull($this->repository->findByToken('de-valid'));
        $this->assertNull($this->repository->findByToken('de-expired'));
    }

    public function testDeleteRemovesToken(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(token: 'delete-me', email: 'del@example.com', invitedBy: $this->therapistId);
        $this->repository->save($invitation);

        $this->repository->delete($invitation);

        $this->assertNull($this->repository->findById($invitation->getId()));
    }
}
