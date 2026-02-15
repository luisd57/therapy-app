<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;

final class DoctrineUserRepositoryTest extends IntegrationTestCase
{
    private UserRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(UserRepositoryInterface::class);
    }

    public function testSaveAndFindById(): void
    {
        $user = DomainTestHelper::createTherapist();
        $this->repository->save($user);

        $found = $this->repository->findById($user->getId());

        $this->assertNotNull($found);
        $this->assertTrue($user->getId()->equals($found->getId()));
        $this->assertSame('therapist@example.com', $found->getEmail()->getValue());
    }

    public function testSaveExistingUserUpdatesFields(): void
    {
        $user = DomainTestHelper::createTherapist();
        $this->repository->save($user);

        $user->updatePassword('new_hashed_pw');
        $this->repository->save($user);

        $found = $this->repository->findById($user->getId());
        $this->assertSame('new_hashed_pw', $found->getPassword());
    }

    public function testFindByIdNonExistentReturnsNull(): void
    {
        $result = $this->repository->findById(UserId::generate());
        $this->assertNull($result);
    }

    public function testFindByEmailExistingReturnsDomainUser(): void
    {
        $user = DomainTestHelper::createTherapist(email: 'find@example.com');
        $this->repository->save($user);

        $found = $this->repository->findByEmail(Email::fromString('find@example.com'));

        $this->assertNotNull($found);
        $this->assertSame('find@example.com', $found->getEmail()->getValue());
    }

    public function testFindByEmailNonExistentReturnsNull(): void
    {
        $result = $this->repository->findByEmail(Email::fromString('notfound@example.com'));
        $this->assertNull($result);
    }

    public function testExistsByEmailExistingReturnsTrue(): void
    {
        $user = DomainTestHelper::createTherapist(email: 'exists@example.com');
        $this->repository->save($user);

        $this->assertTrue($this->repository->existsByEmail(Email::fromString('exists@example.com')));
    }

    public function testExistsByEmailNonExistentReturnsFalse(): void
    {
        $this->assertFalse($this->repository->existsByEmail(Email::fromString('ghost@example.com')));
    }

    public function testFindByRoleReturnsOnlyMatchingRole(): void
    {
        $therapist = DomainTestHelper::createTherapist(email: 'role-t@example.com');
        $patient = DomainTestHelper::createActivePatient(email: 'role-p@example.com');
        $this->repository->save($therapist);
        $this->repository->save($patient);

        $therapists = $this->repository->findByRole(UserRole::THERAPIST);

        $emails = $therapists->map(fn($u) => $u->getEmail()->getValue())->toArray();
        $this->assertContains('role-t@example.com', $emails);
        $this->assertNotContains('role-p@example.com', $emails);
    }

    public function testFindActivePatientsExcludesInactivePatientsAndTherapists(): void
    {
        $therapist = DomainTestHelper::createTherapist(email: 'act-t@example.com');
        $activePatient = DomainTestHelper::createActivePatient(email: 'act-ap@example.com');
        $inactivePatient = DomainTestHelper::createPatient(email: 'act-ip@example.com');
        $this->repository->save($therapist);
        $this->repository->save($activePatient);
        $this->repository->save($inactivePatient);

        $result = $this->repository->findActivePatients();

        $emails = $result->map(fn($u) => $u->getEmail()->getValue())->toArray();
        $this->assertContains('act-ap@example.com', $emails);
        $this->assertNotContains('act-t@example.com', $emails);
        $this->assertNotContains('act-ip@example.com', $emails);
    }

    public function testDeleteRemovesUser(): void
    {
        $user = DomainTestHelper::createTherapist(email: 'delete@example.com');
        $this->repository->save($user);

        $this->repository->delete($user);

        $this->assertNull($this->repository->findById($user->getId()));
    }

    public function testFindSingleTherapistReturnsSingleTherapist(): void
    {
        $therapist = DomainTestHelper::createTherapist(email: 'single-therapist@example.com');
        $this->repository->save($therapist);

        $found = $this->repository->findSingleTherapist();

        $this->assertTrue($therapist->getId()->equals($found->getId()));
        $this->assertSame('single-therapist@example.com', $found->getEmail()->getValue());
    }

    public function testFindSingleTherapistThrowsWhenNoTherapistExists(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->repository->findSingleTherapist();
    }

    public function testFindSingleTherapistThrowsWhenMultipleTherapistsExist(): void
    {
        $therapist1 = DomainTestHelper::createTherapist(email: 'therapist1@example.com');
        $therapist2 = DomainTestHelper::createTherapist(email: 'therapist2@example.com');
        $this->repository->save($therapist1);
        $this->repository->save($therapist2);

        $this->expectException(\RuntimeException::class);

        $this->repository->findSingleTherapist();
    }
}
