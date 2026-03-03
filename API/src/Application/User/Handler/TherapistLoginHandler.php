<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Input\TherapistLoginInputDTO;
use App\Application\User\DTO\Output\AuthResultOutputDTO;
use App\Application\User\DTO\Output\UserOutputDTO;
use App\Domain\User\Exception\InvalidCredentialsException;
use App\Domain\User\Exception\UserNotActiveException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\Service\JwtTokenGeneratorInterface;
use App\Domain\User\Enum\UserRole;

final readonly class TherapistLoginHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private JwtTokenGeneratorInterface $jwtTokenGenerator,
    ) {
    }

    public function __invoke(TherapistLoginInputDTO $dto): AuthResultOutputDTO
    {
        $email = Email::fromString($dto->email);
        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            throw new InvalidCredentialsException();
        }

        if ($user->getRole() !== UserRole::THERAPIST) {
            throw new InvalidCredentialsException();
        }

        if (!$user->isActive()) {
            throw new UserNotActiveException();
        }

        $storedPassword = $user->getPassword();
        if ($storedPassword === null || !$this->passwordHasher->verify($dto->password, $storedPassword)) {
            throw new InvalidCredentialsException();
        }

        $token = $this->jwtTokenGenerator->generate($user);

        return new AuthResultOutputDTO(
            token: $token,
            user: UserOutputDTO::fromEntity($user),
        );
    }
}
