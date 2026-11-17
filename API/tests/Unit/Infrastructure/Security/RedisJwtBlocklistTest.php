<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\RedisJwtBlocklist;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

final class RedisJwtBlocklistTest extends TestCase
{
    private const CUTOFF = 1787306400;

    private RedisJwtBlocklist $blocklist;

    protected function setUp(): void
    {
        $this->blocklist = new RedisJwtBlocklist(new ArrayAdapter());
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

    public function testARevokedJtiExpiresWithItsTtl(): void
    {
        $clock = new MockClock();
        $blocklist = new RedisJwtBlocklist(new ArrayAdapter(clock: $clock));
        $blocklist->revoke('jti-1', 3600);

        $clock->sleep(3599);
        $this->assertTrue($blocklist->isRevoked('jti-1'));

        $clock->sleep(2);
        $this->assertFalse($blocklist->isRevoked('jti-1'));
    }

    public function testACutoffExpiresWithItsTtl(): void
    {
        $clock = new MockClock();
        $blocklist = new RedisJwtBlocklist(new ArrayAdapter(clock: $clock));
        $blocklist->revokeIssuedAtOrBefore('user@example.com', self::CUTOFF, 3600);

        $clock->sleep(3599);
        $this->assertTrue($blocklist->isRevokedByCutoff('user@example.com', self::CUTOFF - 1));

        $clock->sleep(2);
        $this->assertFalse($blocklist->isRevokedByCutoff('user@example.com', self::CUTOFF - 1));
    }
}
