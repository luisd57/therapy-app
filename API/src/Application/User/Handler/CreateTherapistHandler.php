<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Input\CreateTherapistInputDTO;
use App\Application\User\DTO\Output\UserOutputDTO;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\TherapistAlreadyExistsException;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\Id\UserId;
use App\Domain\User\ValueObject\UserRole;

final readonly class CreateTherapistHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
    ) {}

    public function __invoke(CreateTherapistInputDTO $dto): UserOutputDTO
    {
        $existingTherapists = $this->userRepository->findByRole(UserRole::THERAPIST);
        if (!$existingTherapists->isEmpty()) {
            throw new TherapistAlreadyExistsException();
        }

        $email = Email::fromString($dto->email);

        if ($this->userRepository->existsByEmail($email)) {
            throw new UserAlreadyExistsException();
        }

        $hashedPassword = $this->passwordHasher->hash($dto->password);

        $user = User::createTherapist(
            id: UserId::generate(),
            email: $email,
            fullName: $dto->fullName,
            hashedPassword: $hashedPassword,
        );

        $this->userRepository->save($user);

        return UserOutputDTO::fromEntity($user);
    }
}
