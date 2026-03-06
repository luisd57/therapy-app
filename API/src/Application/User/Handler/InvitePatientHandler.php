<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Input\InvitePatientInputDTO;
use App\Application\User\DTO\Output\InvitationOutputDTO;
use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\EmailSenderInterface;
use App\Domain\User\Service\TokenGeneratorInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\Id\TokenId;
use App\Domain\User\Id\UserId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class InvitePatientHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private InvitationTokenRepositoryInterface $invitationRepository,
        private TokenGeneratorInterface $tokenGenerator,
        private EmailSenderInterface $emailSender,
        private string $frontendUrl,
        private int $invitationTtl,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(InvitePatientInputDTO $dto): InvitationOutputDTO
    {
        $email = Email::fromString($dto->email);

        // Check if user already exists
        if ($this->userRepository->existsByEmail($email)) {
            throw new UserAlreadyExistsException();
        }

        // Check if there's already a valid invitation
        $existingInvitation = $this->invitationRepository->findValidByEmail($email);
        if ($existingInvitation !== null) {
            // Return existing valid invitation instead of creating duplicate
            return InvitationOutputDTO::fromEntity($existingInvitation, $this->clock->now());
        }

        $token = $this->tokenGenerator->generate();
        $therapistId = UserId::fromString($dto->therapistId);

        $invitation = InvitationToken::create(
            id: TokenId::generate(),
            token: $token,
            email: $email,
            patientName: $dto->patientName,
            invitedBy: $therapistId,
            ttlSeconds: $this->invitationTtl,
            now: $this->clock->now(),
        );

        $this->invitationRepository->save($invitation);

        // Generate registration URL
        $registrationUrl = sprintf(
            '%s/register?token=%s',
            rtrim($this->frontendUrl, '/'),
            $token,
        );

        try {
            $this->emailSender->sendInvitation(
                to: $email,
                patientName: $dto->patientName,
                registrationUrl: $registrationUrl,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send invitation email: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
                'email_type' => 'patient_invitation',
            ]);
        }

        return InvitationOutputDTO::fromEntity($invitation, $this->clock->now());
    }
}
