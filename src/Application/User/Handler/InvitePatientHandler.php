<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Input\InvitePatientInputDTO;
use App\Application\User\DTO\Output\InvitationDTO;
use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\EmailSenderInterface;
use App\Domain\User\Service\TokenGeneratorInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\TokenId;
use App\Domain\User\ValueObject\UserId;

final readonly class InvitePatientHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private InvitationTokenRepositoryInterface $invitationRepository,
        private TokenGeneratorInterface $tokenGenerator,
        private EmailSenderInterface $emailSender,
        private string $frontendUrl,
        private int $invitationTtl,
    ) {}

    public function handle(InvitePatientInputDTO $input): InvitationDTO
    {
        $email = Email::fromString($input->email);

        // Check if user already exists
        if ($this->userRepository->existsByEmail($email)) {
            throw new UserAlreadyExistsException($input->email);
        }

        // Check if there's already a valid invitation
        $existingInvitation = $this->invitationRepository->findValidByEmail($email);
        if ($existingInvitation !== null) {
            // Return existing valid invitation instead of creating duplicate
            return InvitationDTO::fromEntity($existingInvitation);
        }

        $token = $this->tokenGenerator->generate();
        $therapistId = UserId::fromString($input->therapistId);

        $invitation = InvitationToken::create(
            id: TokenId::generate(),
            token: $token,
            email: $email,
            patientName: $input->patientName,
            invitedBy: $therapistId,
            ttlSeconds: $this->invitationTtl,
        );

        $this->invitationRepository->save($invitation);

        // Generate registration URL
        $registrationUrl = sprintf(
            '%s/register?token=%s',
            rtrim($this->frontendUrl, '/'),
            $token,
        );

        // Send invitation email
        $this->emailSender->sendInvitation(
            to: $email,
            patientName: $input->patientName,
            registrationUrl: $registrationUrl,
        );

        return InvitationDTO::fromEntity($invitation);
    }
}
