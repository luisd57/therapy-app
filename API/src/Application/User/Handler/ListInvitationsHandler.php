<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Output\InvitationOutputDTO;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ListInvitationsHandler
{
    public function __construct(
        private InvitationTokenRepositoryInterface $invitationRepository,
    ) {
    }

    /**
     * @return ArrayCollection<int, InvitationOutputDTO>
     */
    public function __invoke(): ArrayCollection
    {
        $invitations = $this->invitationRepository->findPendingInvitations();

        return $invitations->map(
            fn($invitation) => InvitationOutputDTO::fromEntity($invitation)
        );
    }
}
