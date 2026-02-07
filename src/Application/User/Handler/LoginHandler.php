<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Output\AuthResultDTO;
use App\Application\User\DTO\Output\UserDTO;
use App\Domain\User\Exception\InvalidCredentialsException;
use App\Domain\User\Exception\UserNotActiveException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserRole;

final readonly class LoginHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private JwtTokenGeneratorInterface $jwtTokenGenerator,
    ) {
    }

    public function handleTherapistLogin(string $email, string $password): AuthResultDTO
    {
        return $this->login($email, $password, UserRole::THERAPIST);
    }

    public function handlePatientLogin(string $email, string $password): AuthResultDTO
    {
        return $this->login($email, $password, UserRole::PATIENT);
    }

    private function login(string $emailString, string $password, UserRole $expectedRole): AuthResultDTO
    {
        $email = Email::fromString($emailString);
        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            throw new InvalidCredentialsException();
        }

        if ($user->getRole() !== $expectedRole) {
            throw new InvalidCredentialsException();
        }

        if (!$user->isActive()) {
            throw new UserNotActiveException();
        }

        $storedPassword = $user->getPassword();
        if ($storedPassword === null || !$this->passwordHasher->verify($password, $storedPassword)) {
            throw new InvalidCredentialsException();
        }

        $token = $this->jwtTokenGenerator->generate($user);

        return new AuthResultDTO(
            token: $token,
            user: UserDTO::fromEntity($user),
        );
    }
}
