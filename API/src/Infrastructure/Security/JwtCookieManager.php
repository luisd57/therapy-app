<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\Enum\UserRole;
use Symfony\Component\HttpFoundation\Cookie;

final readonly class JwtCookieManager
{
    public const string THERAPIST_COOKIE_NAME = 'THERAPY_THERAPIST_JWT';
    public const string PATIENT_COOKIE_NAME = 'THERAPY_PATIENT_JWT';
    private const string COOKIE_PATH = '/api';

    public function __construct(
        private int $jwtTokenTtl,
        private bool $jwtCookieSecure,
    ) {}

    public function createCookie(string $token, UserRole $userRole): Cookie
    {
        return $this->buildCookie(
            name: $this->cookieNameForRole($userRole),
            value: $token,
            expires: new \DateTimeImmutable('+' . $this->jwtTokenTtl . ' seconds'),
        );
    }

    /**
     * Returns expired cookies for BOTH roles. Logout clears whichever
     * may be present so the browser is left in a clean state.
     *
     * @return list<Cookie>
     */
    public function createExpiredCookies(): array
    {
        $expiry = new \DateTimeImmutable('1970-01-01');

        return [
            $this->buildCookie(self::THERAPIST_COOKIE_NAME, '', $expiry),
            $this->buildCookie(self::PATIENT_COOKIE_NAME, '', $expiry),
        ];
    }

    public function cookieNameForRole(UserRole $userRole): string
    {
        return $userRole->isTherapist()
            ? self::THERAPIST_COOKIE_NAME
            : self::PATIENT_COOKIE_NAME;
    }

    /**
     * @return list<string>
     */
    public function allCookieNames(): array
    {
        return [self::THERAPIST_COOKIE_NAME, self::PATIENT_COOKIE_NAME];
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
