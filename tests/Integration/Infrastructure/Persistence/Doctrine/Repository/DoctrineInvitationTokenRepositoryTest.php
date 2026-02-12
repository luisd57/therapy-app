<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;

final class DoctrineInvitationTokenRepositoryTest extends IntegrationTestCase
{
    private InvitationTokenRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(InvitationTokenRepositoryInterface::class);
    }

    public function testSaveAndFindById(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(token: 'save-test-token');
        $this->repository->save($invitation);

        $found = $this->repository->findById($invitation->getId());

        $this->assertNotNull($found);
        $this->assertSame('save-test-token', $found->getToken());
    }

    public function testFindByTokenExistingReturnsDomainEntity(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(token: 'find-by-token');
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
        );
        $this->repository->save($used);

        $found = $this->repository->findValidByEmail(Email::fromString('used-only@example.com'));
        $this->assertNull($found);
    }

    public function testFindPendingInvitationsReturnsOnlyValidTokens(): void
    {
        $valid = DomainTestHelper::createValidInvitation(token: 'pending-valid', email: 'pv@example.com');
        $expired = DomainTestHelper::createExpiredInvitation(token: 'pending-expired', email: 'pe@example.com');
        $used = DomainTestHelper::createUsedInvitation(token: 'pending-used', email: 'pu@example.com');

        $this->repository->save($valid);
        $this->repository->save($expired);
        $this->repository->save($used);

        $pending = $this->repository->findPendingInvitations();

        $tokens = $pending->map(fn($inv) => $inv->getToken())->toArray();
        $this->assertContains('pending-valid', $tokens);
        $this->assertNotContains('pending-expired', $tokens);
        $this->assertNotContains('pending-used', $tokens);
    }

    public function testDeleteExpiredRemovesExpiredAndReturnsCount(): void
    {
        $valid = DomainTestHelper::createValidInvitation(token: 'de-valid', email: 'de-v@example.com');
        $expired = DomainTestHelper::createExpiredInvitation(token: 'de-expired', email: 'de-e@example.com');

        $this->repository->save($valid);
        $this->repository->save($expired);

        $count = $this->repository->deleteExpired();

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertNotNull($this->repository->findByToken('de-valid'));
        $this->assertNull($this->repository->findByToken('de-expired'));
    }

    public function testDeleteRemovesToken(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(token: 'delete-me', email: 'del@example.com');
        $this->repository->save($invitation);

        $this->repository->delete($invitation);

        $this->assertNull($this->repository->findById($invitation->getId()));
    }
}
