<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Appointment\Entity;

use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Id\SlotLockId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SlotLockTest extends TestCase
{
    // --- create() ---

    public function testCreateSetsExpiresAtCorrectly(): void
    {
        $beforeCreate = new DateTimeImmutable();

        $lock = SlotLock::create(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            lockToken: 'test-token-123',
            ttlSeconds: 300,
            now: $beforeCreate,
        );

        $afterCreate = new DateTimeImmutable();

        // expiresAt should be approximately now + 300 seconds
        $expectedMin = $beforeCreate->modify('+300 seconds');
        $expectedMax = $afterCreate->modify('+300 seconds');

        $this->assertGreaterThanOrEqual($expectedMin, $lock->getExpiresAt());
        $this->assertLessThanOrEqual($expectedMax, $lock->getExpiresAt());
        $this->assertSame('test-token-123', $lock->getLockToken());
        $this->assertSame(AppointmentModality::ONLINE, $lock->getModality());
    }

    // --- isActive / isExpired ---

    public function testIsActiveForActiveLock(): void
    {
        $now = new DateTimeImmutable();

        $lock = SlotLock::create(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            lockToken: 'active-token',
            ttlSeconds: 3600,
            now: $now,
        );

        $this->assertTrue($lock->isActive($now));
        $this->assertFalse($lock->isExpired($now));
    }

    public function testIsActiveForExpiredLock(): void
    {
        // Use reconstitute to create a lock that already expired
        $lock = SlotLock::reconstitute(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            lockToken: 'expired-token',
            createdAt: new DateTimeImmutable('-1 hour'),
            expiresAt: new DateTimeImmutable('-30 minutes'),
        );

        $now = new DateTimeImmutable();
        $this->assertFalse($lock->isActive($now));
        $this->assertTrue($lock->isExpired($now));
    }

    // --- matchesToken ---

    public function testMatchesTokenWithCorrectToken(): void
    {
        $lock = SlotLock::create(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            lockToken: 'my-secret-token',
            ttlSeconds: 300,
            now: new DateTimeImmutable(),
        );

        $this->assertTrue($lock->matchesToken('my-secret-token'));
    }

    public function testMatchesTokenWithIncorrectToken(): void
    {
        $lock = SlotLock::create(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            lockToken: 'my-secret-token',
            ttlSeconds: 300,
            now: new DateTimeImmutable(),
        );

        $this->assertFalse($lock->matchesToken('wrong-token'));
    }

    public function testMatchesTokenIsCaseSensitive(): void
    {
        $lock = SlotLock::create(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day'), 50),
            modality: AppointmentModality::ONLINE,
            lockToken: 'My-Token',
            ttlSeconds: 300,
            now: new DateTimeImmutable(),
        );

        $this->assertFalse($lock->matchesToken('my-token'));
    }

    // --- reconstitute ---

    public function testReconstituteRestoresAllProperties(): void
    {
        $id = SlotLockId::generate();
        $timeSlot = TimeSlot::create(new DateTimeImmutable('2026-05-01 10:00'), 50);
        $createdAt = new DateTimeImmutable('-1 hour');
        $expiresAt = new DateTimeImmutable('+1 hour');

        $lock = SlotLock::reconstitute(
            id: $id,
            timeSlot: $timeSlot,
            modality: AppointmentModality::IN_PERSON,
            lockToken: 'reconstituted-token',
            createdAt: $createdAt,
            expiresAt: $expiresAt,
        );

        $this->assertTrue($id->equals($lock->getId()));
        $this->assertTrue($timeSlot->equals($lock->getTimeSlot()));
        $this->assertSame(AppointmentModality::IN_PERSON, $lock->getModality());
        $this->assertSame('reconstituted-token', $lock->getLockToken());
        $this->assertSame($createdAt, $lock->getCreatedAt());
        $this->assertSame($expiresAt, $lock->getExpiresAt());
    }
}
