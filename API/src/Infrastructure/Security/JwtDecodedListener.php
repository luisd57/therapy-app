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

        // A claim of the wrong type fails closed: every token we sign carries the right ones.
        if ($jti !== null && (!is_string($jti) || $this->jwtBlocklist->isRevoked($jti))) {
            $jwtDecodedEvent->markAsInvalid();

            return;
        }

        $issuedAt = $payload['iat'] ?? null;
        $email = $payload['email'] ?? null;

        if ($issuedAt !== null && $email !== null
            && (!is_int($issuedAt) || !is_string($email) || $this->jwtBlocklist->isRevokedByCutoff($email, $issuedAt))
        ) {
            $jwtDecodedEvent->markAsInvalid();
        }
    }
}
