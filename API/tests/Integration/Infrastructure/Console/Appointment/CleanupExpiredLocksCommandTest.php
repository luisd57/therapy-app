<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Console\Appointment;

use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Id\SlotLockId;
use App\Domain\Appointment\Repository\SlotLockRepositoryInterface;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Tests\Helper\IntegrationTestCase;
use App\Tests\Helper\UsesUtcInstants;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class CleanupExpiredLocksCommandTest extends IntegrationTestCase
{
    use UsesUtcInstants;

    private SlotLockRepositoryInterface $slotLockRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // 12:00 UTC. The offset catches a query that binds wall time without converting to UTC.
        // Frozen before the repository is resolved, since it takes the clock at construction.
        $this->freezeClock('2026-06-16 02:00:00+14:00');
        $this->slotLockRepository = self::getContainer()->get(SlotLockRepositoryInterface::class);
    }

    private function saveLockExpiringAt(string $lockToken, string $expiresAtUtc): void
    {
        $expiresAt = self::utc($expiresAtUtc);

        $this->slotLockRepository->save(SlotLock::reconstitute(
            id: SlotLockId::generate(),
            timeSlot: TimeSlot::create(self::utc('2026-06-20 09:00:00'), 50),
            modality: AppointmentModality::ONLINE,
            lockToken: $lockToken,
            createdAt: $expiresAt->modify('-10 minutes'),
            expiresAt: $expiresAt,
        ));
    }

    public function testRemovesOnlyLocksPastTheirExpiry(): void
    {
        $this->saveLockExpiringAt('expired-lock', '2026-06-15 11:59:59');
        $this->saveLockExpiringAt('expiring-now-lock', '2026-06-15 12:00:00');
        $this->saveLockExpiringAt('live-lock', '2026-06-15 12:05:00');

        $tester = new CommandTester((new Application(self::$kernel))->find('app:cleanup-slot-locks'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Removed 1 expired slot locks', $tester->getDisplay());
        $this->assertNull($this->slotLockRepository->findByLockToken('expired-lock'));
        $this->assertNotNull($this->slotLockRepository->findByLockToken('expiring-now-lock'));
        $this->assertNotNull($this->slotLockRepository->findByLockToken('live-lock'));
    }
}
