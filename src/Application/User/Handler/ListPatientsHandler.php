<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Output\UserDTO;
use App\Domain\User\Repository\UserRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ListPatientsHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @return ArrayCollection<int, UserDTO>
     */
    public function handle(): ArrayCollection
    {
        $patients = $this->userRepository->findActivePatients();

        return $patients->map(
            fn($user) => UserDTO::fromEntity($user)
        );
    }
}
