<?php

declare (strict_types = 1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Input\ActivatePatientInputDTO;
use App\Application\User\DTO\Output\UserOutputDTO;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\EmailSenderInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Domain\User\ValueObject\UserId;

final readonly class ActivatePatientHandler
{
    public function __construct(
        private InvitationTokenRepositoryInterface $invitationRepository,
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private EmailSenderInterface $emailSender,
    ) {}

    public function __invoke(ActivatePatientInputDTO $dto): UserOutputDTO
    {
        $invitation = $this->invitationRepository->findByToken($dto->token);

        if ($invitation === null) {
            throw InvalidTokenException::notFound();
        }

        if ($invitation->isUsed()) {
            throw InvalidTokenException::alreadyUsed();
        }

        if ($invitation->isExpired()) {
            throw InvalidTokenException::expired();
        }

        // Create the patient user
        $user = User::createPatient(
            id: UserId::generate(),
            email: $invitation->getEmail(),
            fullName: $invitation->getPatientName(),
        );

        // Activate with password
        $hashedPassword = $this->passwordHasher->hash($dto->password);
        $user->activate($hashedPassword);

        // Mark invitation as used
        $invitation->use();

        // Save both entities
        $this->userRepository->save($user);
        $this->invitationRepository->save($invitation);

        // Send welcome email
        $this->emailSender->sendWelcome(
            to: $user->getEmail(),
            userName: $user->getFullName(),
        );

        return UserOutputDTO::fromEntity($user);
    }
}
