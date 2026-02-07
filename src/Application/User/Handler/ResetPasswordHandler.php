<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Input\ResetPasswordInputDTO;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;

final readonly class ResetPasswordHandler
{
    public function __construct(
        private PasswordResetTokenRepositoryInterface $resetTokenRepository,
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
    ) {}

    public function handle(ResetPasswordInputDTO $input): void
    {
        $resetToken = $this->resetTokenRepository->findByToken($input->token);

        if ($resetToken === null) {
            throw InvalidTokenException::notFound();
        }

        if ($resetToken->isUsed()) {
            throw InvalidTokenException::alreadyUsed();
        }

        if ($resetToken->isExpired()) {
            throw InvalidTokenException::expired();
        }

        $user = $this->userRepository->findById($resetToken->getUserId());

        if ($user === null) {
            throw new UserNotFoundException($resetToken->getUserId()->getValue());
        }

        // Update password
        $hashedPassword = $this->passwordHasher->hash($input->newPassword);
        $user->updatePassword($hashedPassword);

        // Mark token as used
        $resetToken->use();

        // Save changes
        $this->userRepository->save($user);
        $this->resetTokenRepository->save($resetToken);
    }
}
