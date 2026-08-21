<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\RedisJwtBlocklist;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

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

        $this->assertTrue($this->blocklist->isIssuedAtOrBefore('user@example.com', self::CUTOFF - 1));
    }

    /**
     * iat has second resolution, so a token minted in the same second as the reset
     * has to fall on the revoked side or the cutoff has a one-second hole.
     */
    public function testATokenIssuedInTheSameSecondAsTheCutoffIsRejected(): void
    {
        $this->blocklist->revokeIssuedAtOrBefore('user@example.com', self::CUTOFF, 3600);

        $this->assertTrue($this->blocklist->isIssuedAtOrBefore('user@example.com', self::CUTOFF));
    }

    public function testATokenIssuedAfterTheCutoffSurvives(): void
    {
        $this->blocklist->revokeIssuedAtOrBefore('user@example.com', self::CUTOFF, 3600);

        $this->assertFalse($this->blocklist->isIssuedAtOrBefore('user@example.com', self::CUTOFF + 1));
    }

    public function testACutoffOnlyAppliesToItsOwnUser(): void
    {
        $this->blocklist->revokeIssuedAtOrBefore('user@example.com', self::CUTOFF, 3600);

        $this->assertFalse($this->blocklist->isIssuedAtOrBefore('other@example.com', self::CUTOFF - 1));
    }

    public function testNoCutoffMeansNothingIsRejected(): void
    {
        $this->assertFalse($this->blocklist->isIssuedAtOrBefore('user@example.com', self::CUTOFF));
    }

    public function testJtiRevocationStillWorksAlongsideCutoffs(): void
    {
        $this->blocklist->revoke('jti-1', 3600);
        $this->blocklist->revokeIssuedAtOrBefore('user@example.com', self::CUTOFF, 3600);

        $this->assertTrue($this->blocklist->isRevoked('jti-1'));
        $this->assertFalse($this->blocklist->isRevoked('jti-2'));
    }
}
