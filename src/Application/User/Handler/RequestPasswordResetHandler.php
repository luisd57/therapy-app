<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Input\RequestPasswordResetInputDTO;
use App\Domain\User\Entity\PasswordResetToken;
use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\EmailSenderInterface;
use App\Domain\User\Service\TokenGeneratorInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\TokenId;

final readonly class RequestPasswordResetHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordResetTokenRepositoryInterface $resetTokenRepository,
        private TokenGeneratorInterface $tokenGenerator,
        private EmailSenderInterface $emailSender,
        private string $frontendUrl,
        private int $passwordResetTtl,
    ) {}

    /**
     * Note: Always returns void to prevent email enumeration attacks.
     * Even if the user doesn't exist, we don't reveal this information.
     */
    public function handle(RequestPasswordResetInputDTO $input): void
    {
        $email = Email::fromString($input->email);
        $user = $this->userRepository->findByEmail($email);

        // Silently return if user doesn't exist (prevents email enumeration)
        if ($user === null || !$user->isActive()) {
            return;
        }

        // Invalidate any existing tokens for this user
        $this->resetTokenRepository->invalidateAllForUser($user->getId());

        $token = $this->tokenGenerator->generate();

        $resetToken = PasswordResetToken::create(
            id: TokenId::generate(),
            token: $token,
            userId: $user->getId(),
            ttlSeconds: $this->passwordResetTtl,
        );

        $this->resetTokenRepository->save($resetToken);

        // Generate reset URL
        $resetUrl = sprintf(
            '%s/reset-password?token=%s',
            rtrim($this->frontendUrl, '/'),
            $token,
        );

        $this->emailSender->sendPasswordReset(
            to: $email,
            resetUrl: $resetUrl,
        );
    }
}
