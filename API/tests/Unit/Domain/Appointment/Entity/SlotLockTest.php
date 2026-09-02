<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Appointment\Entity;

use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Id\SlotLockId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Tests\Helper\AssertsInstants;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * SlotLock takes now as an ordinary argument, so time here is a literal rather than
 * a frozen clock the entity never consults. Reading the wall clock either side of
 * create() and asserting the expiry falls between them cannot disagree with the
 * entity: both sums come from the same source. See ADR-0003.
 */
final class SlotLockTest extends TestCase
{
    use AssertsInstants;

    private const NOW = '2026-05-01T12:00:00+00:00';

    private static function utc(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('UTC'));
    }

    private static function aTimeSlot(): TimeSlot
    {
        return TimeSlot::create(self::utc('2026-05-02 09:00'), 50);
    }

    // --- create() ---

    public function testCreateSetsExpiresAtToNowPlusTheTtl(): void
    {
        $lock = SlotLock::create(
            id: SlotLockId::generate(),
            timeSlot: self::aTimeSlot(),
            modality: AppointmentModality::ONLINE,
            lockToken: 'test-token-123',
            ttlSeconds: 300,
            now: new DateTimeImmutable(self::NOW),
        );

        self::assertInstantIs('2026-05-01T12:00:00+00:00', $lock->getCreatedAt());
        self::assertInstantIs('2026-05-01T12:05:00+00:00', $lock->getExpiresAt());
        $this->assertSame('test-token-123', $lock->getLockToken());
        $this->assertSame(AppointmentModality::ONLINE, $lock->getModality());
    }

    /**
     * The TTL is counted in seconds from the instant given, not from the caller's
     * wall clock, so an offset in the argument must carry through.
     */
    public function testCreateCountsTheTtlFromTheInstantItIsGiven(): void
    {
        $lock = SlotLock::create(
            id: SlotLockId::generate(),
            timeSlot: self::aTimeSlot(),
            modality: AppointmentModality::ONLINE,
            lockToken: 'offset-token',
            ttlSeconds: 600,
            now: new DateTimeImmutable('2026-05-01T08:00:00-04:00'),
        );

        self::assertInstantIs('2026-05-01T12:10:00+00:00', $lock->getExpiresAt());
    }

    // --- isActive / isExpired ---

    public function testALockIsActiveUpToItsExpiryAndExpiredAfterIt(): void
    {
        $lock = SlotLock::create(
            id: SlotLockId::generate(),
            timeSlot: self::aTimeSlot(),
            modality: AppointmentModality::ONLINE,
            lockToken: 'active-token',
            ttlSeconds: 3600,
            now: new DateTimeImmutable(self::NOW),
        );

        // Expiry is 13:00. isExpired compares with <, so the expiry instant itself is
        // still active and the second after it is not.
        $this->assertTrue($lock->isActive(new DateTimeImmutable('2026-05-01T12:59:59+00:00')));
        $this->assertTrue($lock->isActive(new DateTimeImmutable('2026-05-01T13:00:00+00:00')));
        $this->assertFalse($lock->isActive(new DateTimeImmutable('2026-05-01T13:00:01+00:00')));
        $this->assertTrue($lock->isExpired(new DateTimeImmutable('2026-05-01T13:00:01+00:00')));
    }

    public function testIsActiveForExpiredLock(): void
    {
        // reconstitute is the only way to build a lock that already expired
        $lock = SlotLock::reconstitute(
            id: SlotLockId::generate(),
            timeSlot: self::aTimeSlot(),
            modality: AppointmentModality::ONLINE,
            lockToken: 'expired-token',
            createdAt: new DateTimeImmutable('2026-05-01T11:00:00+00:00'),
            expiresAt: new DateTimeImmutable('2026-05-01T11:30:00+00:00'),
        );

        $now = new DateTimeImmutable(self::NOW);
        $this->assertFalse($lock->isActive($now));
        $this->assertTrue($lock->isExpired($now));
    }

    // --- reconstitute ---

    public function testReconstituteRestoresAllProperties(): void
    {
        $id = SlotLockId::generate();
        $timeSlot = TimeSlot::create(self::utc('2026-05-01 10:00'), 50);
        $createdAt = new DateTimeImmutable('2026-05-01T11:00:00+00:00');
        $expiresAt = new DateTimeImmutable('2026-05-01T13:00:00+00:00');

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
