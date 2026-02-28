<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

final class JwtCreatedListener
{
    public function onJWTCreated(JWTCreatedEvent $jwtCreatedEvent): void
    {
        $payload = $jwtCreatedEvent->getData();
        $payload['jti'] = bin2hex(random_bytes(16));
        $payload['email'] = $jwtCreatedEvent->getUser()->getUserIdentifier();
        $jwtCreatedEvent->setData($payload);
    }
}
