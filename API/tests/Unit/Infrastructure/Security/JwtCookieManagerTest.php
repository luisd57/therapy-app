<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\JwtCookieManager;
use PHPUnit\Framework\TestCase;

/**
 * The flags are the session security posture. The container binds jwtCookieSecure from
 * %app.jwt_cookie_secure%, false under test, so Secure: true is only reachable from here.
 */
final class JwtCookieManagerTest extends TestCase
{
    private const int TTL = 3600;

    public function testTheSessionCookieCarriesTheTokenUnderTheExpectedName(): void
    {
        $cookie = $this->manager()->createCookie('a.jwt.token');

        $this->assertSame(JwtCookieManager::COOKIE_NAME, $cookie->getName());
        $this->assertSame('a.jwt.token', $cookie->getValue());
    }

    public function testTheSessionCookieIsHttpOnly(): void
    {
        $this->assertTrue($this->manager()->createCookie('a.jwt.token')->isHttpOnly());
    }

    public function testTheSessionCookieIsSameSiteLax(): void
    {
        $this->assertSame('lax', $this->manager()->createCookie('a.jwt.token')->getSameSite());
    }

    /**
     * A wider path would hand the token to every route on the origin, the landing site included.
     */
    public function testTheSessionCookieIsScopedToTheApi(): void
    {
        $this->assertSame('/api', $this->manager()->createCookie('a.jwt.token')->getPath());
    }

    public function testTheSessionCookieIsSecureWhenConfiguredToBe(): void
    {
        $this->assertTrue($this->manager(jwtCookieSecure: true)->createCookie('a.jwt.token')->isSecure());
    }

    /**
     * Both directions, or the assertion above would also pass against a hardcoded true.
     */
    public function testTheSessionCookieIsNotSecureWhenConfiguredNotToBe(): void
    {
        $this->assertFalse($this->manager(jwtCookieSecure: false)->createCookie('a.jwt.token')->isSecure());
    }

    public function testTheSessionCookieExpiresAfterTheConfiguredTtl(): void
    {
        $before = time();

        $cookie = $this->manager()->createCookie('a.jwt.token');

        // A window, not an equality: createCookie reads the wall clock.
        $this->assertGreaterThanOrEqual($before + self::TTL, $cookie->getExpiresTime());
        $this->assertLessThanOrEqual(time() + self::TTL, $cookie->getExpiresTime());
    }

    public function testTheClearingCookieIsEmptyAndAlreadyExpired(): void
    {
        $cookie = $this->manager()->createExpiredCookie();

        $this->assertSame(JwtCookieManager::COOKIE_NAME, $cookie->getName());
        $this->assertSame('', $cookie->getValue());
        $this->assertLessThan(time(), $cookie->getExpiresTime());
    }

    /**
     * A browser matches a clear against name, path and the rest. Attributes that drift from
     * createCookie leave the original cookie in place and logout stops clearing anything.
     */
    public function testTheClearingCookieMatchesTheScopeOfTheOneItReplaces(): void
    {
        $manager = $this->manager(jwtCookieSecure: true);
        $session = $manager->createCookie('a.jwt.token');
        $cleared = $manager->createExpiredCookie();

        $this->assertSame($session->getPath(), $cleared->getPath());
        $this->assertSame($session->isHttpOnly(), $cleared->isHttpOnly());
        $this->assertSame($session->getSameSite(), $cleared->getSameSite());
        $this->assertSame($session->isSecure(), $cleared->isSecure());
    }

    private function manager(int $jwtTokenTtl = self::TTL, bool $jwtCookieSecure = false): JwtCookieManager
    {
        return new JwtCookieManager($jwtTokenTtl, $jwtCookieSecure);
    }
}
