<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Input\ResendInvitationInputDTO;
use App\Application\User\DTO\Output\InvitationOutputDTO;
use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\Exception\InvitationNotFoundException;
use App\Domain\User\Id\TokenId;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\Service\EmailSenderInterface;
use App\Domain\User\Service\TokenGeneratorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class ResendInvitationHandler
{
    public function __construct(
        private InvitationTokenRepositoryInterface $invitationRepository,
        private TokenGeneratorInterface $tokenGenerator,
        private EmailSenderInterface $emailSender,
        private string $frontendUrl,
        private int $invitationTtl,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ResendInvitationInputDTO $dto): InvitationOutputDTO
    {
        $tokenId = TokenId::fromString($dto->tokenId);
        $original = $this->invitationRepository->findById($tokenId);

        if ($original === null) {
            throw new InvitationNotFoundException($dto->tokenId);
        }

        $now = $this->clock->now();

        $original->revoke($now);
        $this->invitationRepository->save($original);

        $rawToken = $this->tokenGenerator->generate();
        $newInvitation = InvitationToken::create(
            id: TokenId::generate(),
            token: $rawToken,
            email: $original->getEmail(),
            patientName: $original->getPatientName(),
            invitedBy: $original->getInvitedBy(),
            ttlSeconds: $this->invitationTtl,
            now: $now,
        );
        $this->invitationRepository->save($newInvitation);

        $registrationUrl = sprintf(
            '%s/register?token=%s',
            rtrim($this->frontendUrl, '/'),
            $rawToken,
        );

        try {
            $this->emailSender->sendInvitation(
                to: $newInvitation->getEmail(),
                patientName: $newInvitation->getPatientName(),
                registrationUrl: $registrationUrl,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send invitation email: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
                'email_type' => 'patient_invitation_resend',
            ]);
        }

        return InvitationOutputDTO::fromEntity($newInvitation, $now);
    }
}
