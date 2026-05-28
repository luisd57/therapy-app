<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class MultiCookieTokenExtractor implements TokenExtractorInterface
{
    public function __construct(
        private JwtCookieManager $jwtCookieManager,
    ) {}

    public function extract(Request $request): string|false
    {
        foreach ($this->jwtCookieManager->allCookieNames() as $cookieName) {
            $value = $request->cookies->get($cookieName);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return false;
    }
}
