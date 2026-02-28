<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Output\UserOutputDTO;use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Id\UserId;

final readonly class GetUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(string $userId): UserOutputDTO
    {
        $id = UserId::fromString($userId);
        $user = $this->userRepository->findById($id);

        if ($user === null) {
            throw new UserNotFoundException($userId);
        }

        return UserOutputDTO::fromEntity($user);
    }
}
