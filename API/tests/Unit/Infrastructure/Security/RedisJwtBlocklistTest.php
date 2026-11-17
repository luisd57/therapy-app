<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\RedisJwtBlocklist;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

final class RedisJwtBlocklistTest extends TestCase
{
    private const CUTOFF = 1787306400;

    private MockClock $clock;

    private RedisJwtBlocklist $blocklist;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new \DateTimeImmutable('2026-11-17 09:00:00', new \DateTimeZone('UTC')));
        $this->blocklist = new RedisJwtBlocklist(new ArrayAdapter(clock: $this->clock));
    }

    /**
     * Two TTLs, so a hardcoded expiry cannot match both.
     *
     * @return iterable<string, array{int}>
     */
    public static function ttls(): iterable
    {
        yield 'one minute' => [60];
        yield 'one hour' => [3600];
    }

    public function testATokenIssuedBeforeTheCutoffIsRejected(): void
    {
        $this->blocklist->revokeIssuedAtOrBefore('user@example.com', self::CUTOFF, 3600);

        $this->assertTrue($this->blocklist->isRevokedByCutoff('user@example.com', self::CUTOFF - 1));
    }

    /**
     * iat has second resolution, so a token minted in the same second as the reset
     * has to fall on the revoked side or the cutoff has a one-second hole.
     */
    public function testATokenIssuedInTheSameSecondAsTheCutoffIsRejected(): void
    {
        $this->blocklist->revokeIssuedAtOrBefore('user@example.com', self::CUTOFF, 3600);

        $this->assertTrue($this->blocklist->isRevokedByCutoff('user@example.com', self::CUTOFF));
    }

    public function testATokenIssuedAfterTheCutoffSurvives(): void
    {
        $this->blocklist->revokeIssuedAtOrBefore('user@example.com', self::CUTOFF, 3600);

        $this->assertFalse($this->blocklist->isRevokedByCutoff('user@example.com', self::CUTOFF + 1));
    }

    public function testACutoffOnlyAppliesToItsOwnUser(): void
    {
        $this->blocklist->revokeIssuedAtOrBefore('user@example.com', self::CUTOFF, 3600);

        $this->assertFalse($this->blocklist->isRevokedByCutoff('other@example.com', self::CUTOFF - 1));
    }

    public function testNoCutoffMeansNothingIsRejected(): void
    {
        $this->assertFalse($this->blocklist->isRevokedByCutoff('user@example.com', self::CUTOFF));
    }

    public function testJtiRevocationStillWorksAlongsideCutoffs(): void
    {
        $this->blocklist->revoke('jti-1', 3600);
        $this->blocklist->revokeIssuedAtOrBefore('user@example.com', self::CUTOFF, 3600);

        $this->assertTrue($this->blocklist->isRevoked('jti-1'));
        $this->assertFalse($this->blocklist->isRevoked('jti-2'));
    }

    /** Gone at exactly the TTL, as Redis expires it, so a one-second-longer TTL fails. */
    #[DataProvider('ttls')]
    public function testARevokedJtiExpiresWithItsTtl(int $ttlSeconds): void
    {
        $this->blocklist->revoke('jti-1', $ttlSeconds);

        $this->clock->sleep($ttlSeconds - 1);
        $this->assertTrue($this->blocklist->isRevoked('jti-1'));

        $this->clock->sleep(1);
        $this->assertFalse($this->blocklist->isRevoked('jti-1'));
    }

    #[DataProvider('ttls')]
    public function testACutoffExpiresWithItsTtl(int $ttlSeconds): void
    {
        $this->blocklist->revokeIssuedAtOrBefore('user@example.com', self::CUTOFF, $ttlSeconds);

        $this->clock->sleep($ttlSeconds - 1);
        $this->assertTrue($this->blocklist->isRevokedByCutoff('user@example.com', self::CUTOFF - 1));

        $this->clock->sleep(1);
        $this->assertFalse($this->blocklist->isRevokedByCutoff('user@example.com', self::CUTOFF - 1));
    }
}
