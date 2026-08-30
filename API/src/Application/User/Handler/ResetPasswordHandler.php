<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Input\ResetPasswordInputDTO;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\JwtBlocklistInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class ResetPasswordHandler
{
    public function __construct(
        private PasswordResetTokenRepositoryInterface $resetTokenRepository,
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private JwtBlocklistInterface $jwtBlocklist,
        private ClockInterface $clock,
        private int $jwtTokenTtl,
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

        $user = $resetToken->getUser();

        // Update password
        $hashedPassword = $this->passwordHasher->hash($dto->newPassword);
        $user->updatePassword($hashedPassword, $now);

        // Mark token as used
        $resetToken->use($now);

        // Save changes
        $this->userRepository->save($user);
        $this->resetTokenRepository->save($resetToken);

        // A reset is how a stolen session gets cut off, so sessions older than it
        // have to die with it. iat has second resolution, hence at-or-before.
        $this->jwtBlocklist->revokeIssuedAtOrBefore(
            $user->getEmail()->getValue(),
            $now->getTimestamp(),
            $this->jwtTokenTtl,
        );
    }
}
