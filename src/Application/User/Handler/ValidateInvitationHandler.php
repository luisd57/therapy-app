<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Output\InvitationDTO;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;

final readonly class ValidateInvitationHandler
{
    public function __construct(
        private InvitationTokenRepositoryInterface $invitationRepository,
    ) {
    }

    public function handle(string $token): InvitationDTO
    {
        $invitation = $this->invitationRepository->findByToken($token);

        if ($invitation === null) {
            throw InvalidTokenException::notFound();
        }

        if ($invitation->isUsed()) {
            throw InvalidTokenException::alreadyUsed();
        }

        if ($invitation->isExpired()) {
            throw InvalidTokenException::expired();
        }

        return InvitationDTO::fromEntity($invitation);
    }
}
