<?php

declare(strict_types=1);

namespace App\Domain\User\Service;

use App\Domain\User\Entity\User;

interface JwtTokenGeneratorInterface
{
    public function generate(User $user): string;
}
