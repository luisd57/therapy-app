<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\Service\JwtBlocklistInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;

final class JwtDecodedListener
{
    public function __construct(
        private readonly JwtBlocklistInterface $jwtBlocklist,
    ) {}

    public function onJWTDecoded(JWTDecodedEvent $jwtDecodedEvent): void
    {
        $payload = $jwtDecodedEvent->getPayload();
        $jti = $payload['jti'] ?? null;

        if ($jti !== null && $this->jwtBlocklist->isRevoked($jti)) {
            $jwtDecodedEvent->markAsInvalid();
        }
    }
}
