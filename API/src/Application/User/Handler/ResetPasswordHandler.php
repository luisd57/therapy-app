<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Input\ResetPasswordInputDTO;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class ResetPasswordHandler
{
    public function __construct(
        private PasswordResetTokenRepositoryInterface $resetTokenRepository,
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ResetPasswordInputDTO $dto): void
    {
        $now = $this->clock->now();

        $resetToken = $this->resetTokenRepository->findByToken($dto->token);

        if ($resetToken === null) {
            throw InvalidTokenException::notFound();
        }

        if ($resetToken->isUsed()) {
            throw InvalidTokenException::alreadyUsed();
        }

        if ($resetToken->isExpired($now)) {
            throw InvalidTokenException::expired();
        }

        $user = $this->userRepository->findById($resetToken->getUserId());

        if ($user === null) {
            throw new UserNotFoundException($resetToken->getUserId()->getValue());
        }

        // Update password
        $hashedPassword = $this->passwordHasher->hash($dto->newPassword);
        $user->updatePassword($hashedPassword, $now);

        // Mark token as used
        $resetToken->use($now);

        // Save changes
        $this->userRepository->save($user);
        $this->resetTokenRepository->save($resetToken);
    }
}
