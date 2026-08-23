<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Domain\User\Entity\User;

trait ResolvesCurrentUserTrait
{
    /**
     * The authenticated user's id. Actions behind #[IsGranted] always have one.
     */
    protected function currentUserId(): string
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        return $currentUser->getId()->getValue();
    }
}
