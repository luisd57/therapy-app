<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Cookie;

final readonly class JwtCookieManager
{
    private const string COOKIE_NAME = 'THERAPY_JWT';
    private const string COOKIE_PATH = '/api';

    public function __construct(
        private int $jwtTokenTtl,
        private bool $jwtCookieSecure,
    ) {}

    public function createCookie(string $token): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)
            ->withValue($token)
            ->withPath(self::COOKIE_PATH)
            ->withHttpOnly(true)
            ->withSecure($this->jwtCookieSecure)
            ->withSameSite('lax')
            ->withExpires(new \DateTimeImmutable('+' . $this->jwtTokenTtl . ' seconds'));
    }

    public function createExpiredCookie(): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)
            ->withValue('')
            ->withPath(self::COOKIE_PATH)
            ->withHttpOnly(true)
            ->withSecure($this->jwtCookieSecure)
            ->withSameSite('lax')
            ->withExpires(new \DateTimeImmutable('1970-01-01'));
    }

    public function getCookieName(): string
    {
        return self::COOKIE_NAME;
    }
}
