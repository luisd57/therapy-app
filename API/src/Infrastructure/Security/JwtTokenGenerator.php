<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\Service\JwtTokenGeneratorInterface;
use App\Domain\User\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final readonly class JwtTokenGenerator implements JwtTokenGeneratorInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function generate(User $user): string
    {
        return $this->jwtManager->create($user);
    }
}