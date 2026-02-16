<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\User\Handler\JwtTokenGeneratorInterface;
use App\Domain\User\Entity\User;
use App\Infrastructure\Persistence\Doctrine\User\Mapper\UserMapper;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final readonly class JwtTokenGenerator implements JwtTokenGeneratorInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function generate(User $user): string
    {
        $userEntity = UserMapper::toEntity($user);
        
        return $this->jwtManager->create($userEntity);
    }
}