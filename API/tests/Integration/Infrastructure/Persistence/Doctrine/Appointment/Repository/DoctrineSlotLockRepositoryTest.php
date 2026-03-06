<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Appointment\Repository;

use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Repository\SlotLockRepositoryInterface;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Id\SlotLockId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Tests\Helper\IntegrationTestCase;
use DateTimeImmutable;

final class DoctrineSlotLockRepositoryTest extends IntegrationTestCase
{
    private SlotLockRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(SlotLockRepositoryInterface::class);
    }

    public function testSaveAndFindByLockToken(): void
    {
        $lock = SlotLock::create(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('2026-06-02 09:00:00'), 50),
            modality: AppointmentModality::ONLINE,
            lockToken: 'test-lock-token-123',
            ttlSeconds: 600,
            now: new DateTimeImmutable(),
        );
        $this->repository->save($lock);

        $this->entityManager->clear();

        $found = $this->repository->findByLockToken('test-lock-token-123');

        $this->assertNotNull($found);
        $this->assertTrue($lock->getId()->equals($found->getId()));
        $this->assertSame(hash('sha256', 'test-lock-token-123'), $found->getLockToken());
        $this->assertSame(AppointmentModality::ONLINE, $found->getModality());
    }

    public function testFindByLockTokenNonExistentReturnsNull(): void
    {
        $result = $this->repository->findByLockToken('nonexistent-token');
        $this->assertNull($result);
    }

    public function testFindActiveByTimeSlotReturnsActiveLock(): void
    {
        $activeLock = SlotLock::create(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('2026-06-02 09:00:00'), 50),
            modality: AppointmentModality::ONLINE,
            lockToken: 'active-token',
            ttlSeconds: 600,
            now: new DateTimeImmutable(),
        );
        $this->repository->save($activeLock);

        $found = $this->repository->findActiveByTimeSlot(
            new DateTimeImmutable('2026-06-02 09:00:00'),
            new DateTimeImmutable('2026-06-02 09:50:00'),
        );

        $this->assertNotNull($found);
        $this->assertTrue($activeLock->getId()->equals($found->getId()));
    }

    public function testFindActiveByTimeSlotIgnoresExpiredLock(): void
    {
        $expiredLock = SlotLock::reconstitute(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('2026-06-02 10:00:00'), 50),
            modality: AppointmentModality::ONLINE,
            lockToken: 'expired-token',
            createdAt: new DateTimeImmutable('-20 minutes'),
            expiresAt: new DateTimeImmutable('-10 minutes'),
        );
        $this->repository->save($expiredLock);

        $found = $this->repository->findActiveByTimeSlot(
            new DateTimeImmutable('2026-06-02 10:00:00'),
            new DateTimeImmutable('2026-06-02 10:50:00'),
        );

        $this->assertNull($found);
    }

    public function testDeleteExpiredRemovesOnlyExpiredLocks(): void
    {
        $activeLock = SlotLock::create(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('2026-06-02 09:00:00'), 50),
            modality: AppointmentModality::ONLINE,
            lockToken: 'keep-active-token',
            ttlSeconds: 600,
            now: new DateTimeImmutable(),
        );
        $this->repository->save($activeLock);

        $expiredLock = SlotLock::reconstitute(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('2026-06-02 11:00:00'), 50),
            modality: AppointmentModality::IN_PERSON,
            lockToken: 'remove-expired-token',
            createdAt: new DateTimeImmutable('-30 minutes'),
            expiresAt: new DateTimeImmutable('-15 minutes'),
        );
        $this->repository->save($expiredLock);

        $deletedCount = $this->repository->deleteExpired();

        $this->assertGreaterThanOrEqual(1, $deletedCount);
        $this->assertNotNull($this->repository->findByLockToken('keep-active-token'));
        $this->assertNull($this->repository->findByLockToken('remove-expired-token'));
    }

    public function testDeleteRemovesLock(): void
    {
        $lock = SlotLock::create(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('2026-06-02 14:00:00'), 50),
            modality: AppointmentModality::IN_PERSON,
            lockToken: 'delete-me-token',
            ttlSeconds: 600,
            now: new DateTimeImmutable(),
        );
        $this->repository->save($lock);

        $this->repository->delete($lock);

        $this->assertNull($this->repository->findByLockToken('delete-me-token'));
    }
}
