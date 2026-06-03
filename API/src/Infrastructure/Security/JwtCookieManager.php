<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Cookie;

final readonly class JwtCookieManager
{
    public const string COOKIE_NAME = 'THERAPY_JWT';
    private const string COOKIE_PATH = '/api';

    public function __construct(
        private int $jwtTokenTtl,
        private bool $jwtCookieSecure,
    ) {}

    public function createCookie(string $token): Cookie
    {
        return $this->buildCookie(
            name: self::COOKIE_NAME,
            value: $token,
            expires: new \DateTimeImmutable('+' . $this->jwtTokenTtl . ' seconds'),
        );
    }

    public function createExpiredCookie(): Cookie
    {
        return $this->buildCookie(self::COOKIE_NAME, '', new \DateTimeImmutable('1970-01-01'));
    }

    private function buildCookie(string $name, string $value, \DateTimeImmutable $expires): Cookie
    {
        return Cookie::create($name)
            ->withValue($value)
            ->withPath(self::COOKIE_PATH)
            ->withHttpOnly(true)
            ->withSecure($this->jwtCookieSecure)
            ->withSameSite('lax')
            ->withExpires($expires);
    }
}
