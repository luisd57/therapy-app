<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Domain\User\Entity\User;

interface JwtTokenGeneratorInterface
{
    public function generate(User $user): string;
}
