<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Input\RevokeInvitationInputDTO;
use App\Application\User\DTO\Output\InvitationOutputDTO;
use App\Domain\User\Exception\InvitationNotFoundException;
use App\Domain\User\Id\TokenId;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class RevokeInvitationHandler
{
    public function __construct(
        private InvitationTokenRepositoryInterface $invitationRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokeInvitationInputDTO $dto): InvitationOutputDTO
    {
        $tokenId = TokenId::fromString($dto->tokenId);
        $invitation = $this->invitationRepository->findById($tokenId);

        if ($invitation === null) {
            throw new InvitationNotFoundException($dto->tokenId);
        }

        $now = $this->clock->now();
        $invitation->revoke($now);
        $this->invitationRepository->save($invitation);

        return InvitationOutputDTO::fromEntity($invitation, $now);
    }
}
