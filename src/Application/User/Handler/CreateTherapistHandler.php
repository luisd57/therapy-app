<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Input\CreateTherapistInputDTO;
use App\Application\User\DTO\Output\UserDTO;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;

final readonly class CreateTherapistHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
    ) {}

    public function handle(CreateTherapistInputDTO $input): UserDTO
    {
        $email = Email::fromString($input->email);

        if ($this->userRepository->existsByEmail($email)) {
            throw new UserAlreadyExistsException($input->email);
        }

        $hashedPassword = $this->passwordHasher->hash($input->password);

        $user = User::createTherapist(
            id: UserId::generate(),
            email: $email,
            fullName: $input->fullName,
            hashedPassword: $hashedPassword,
        );

        $this->userRepository->save($user);

        return UserDTO::fromEntity($user);
    }
}
