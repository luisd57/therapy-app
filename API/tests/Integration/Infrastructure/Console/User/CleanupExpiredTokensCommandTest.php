<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Console\User;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;
use App\Tests\Helper\UsesUtcInstants;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class CleanupExpiredTokensCommandTest extends IntegrationTestCase
{
    use UsesUtcInstants;

    private InvitationTokenRepositoryInterface $invitationRepository;
    private PasswordResetTokenRepositoryInterface $passwordResetRepository;
    private User $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        // 12:00 UTC. The offset catches a query that binds wall time without converting to UTC.
        // Frozen before the repositories are resolved, since they take the clock at construction.
        $this->freezeClock('2026-06-16 02:00:00+14:00');
        $this->invitationRepository = self::getContainer()->get(InvitationTokenRepositoryInterface::class);
        $this->passwordResetRepository = self::getContainer()->get(PasswordResetTokenRepositoryInterface::class);

        $this->therapist = DomainTestHelper::createTherapist();
        self::getContainer()->get(UserRepositoryInterface::class)->save($this->therapist);
    }

    private function saveInvitationExpiringAt(string $token, string $expiresAtUtc): void
    {
        $this->invitationRepository->save(DomainTestHelper::createBoundaryInvitation(
            token: $token,
            email: $token . '@test.com',
            invitedBy: $this->therapist,
            expiresAt: self::utc($expiresAtUtc),
        ));
    }

    private function savePasswordResetExpiringAt(string $token, string $expiresAtUtc): void
    {
        $this->passwordResetRepository->save(DomainTestHelper::createValidPasswordResetToken(
            token: $token,
            user: $this->therapist,
            ttlSeconds: 3600,
            now: self::utc($expiresAtUtc)->modify('-1 hour'),
        ));
    }

    public function testRemovesOnlyTokensPastTheirExpiry(): void
    {
        // Two expired invitations against one reset, so the message cannot swap the counts
        $this->saveInvitationExpiringAt('expired-invitation', '2026-06-15 11:59:59');
        $this->saveInvitationExpiringAt('long-expired-invitation', '2026-06-01 09:00:00');
        $this->saveInvitationExpiringAt('expiring-now-invitation', '2026-06-15 12:00:00');
        $this->saveInvitationExpiringAt('live-invitation', '2026-06-16 12:00:00');
        $this->savePasswordResetExpiringAt('expired-reset', '2026-06-15 11:59:59');
        $this->savePasswordResetExpiringAt('expiring-now-reset', '2026-06-15 12:00:00');
        $this->savePasswordResetExpiringAt('live-reset', '2026-06-15 13:00:00');

        $tester = new CommandTester((new Application(self::$kernel))->find('app:cleanup-tokens'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        // SymfonyStyle wraps the line at the terminal width
        $this->assertStringContainsString(
            'Removed 2 expired invitation tokens and 1 expired password reset tokens',
            preg_replace('/\s+/', ' ', $tester->getDisplay()),
        );
        $this->assertNull($this->invitationRepository->findByToken('expired-invitation'));
        $this->assertNull($this->invitationRepository->findByToken('long-expired-invitation'));
        $this->assertNotNull($this->invitationRepository->findByToken('expiring-now-invitation'));
        $this->assertNotNull($this->invitationRepository->findByToken('live-invitation'));
        $this->assertNull($this->passwordResetRepository->findByToken('expired-reset'));
        $this->assertNotNull($this->passwordResetRepository->findByToken('expiring-now-reset'));
        $this->assertNotNull($this->passwordResetRepository->findByToken('live-reset'));
    }
}
