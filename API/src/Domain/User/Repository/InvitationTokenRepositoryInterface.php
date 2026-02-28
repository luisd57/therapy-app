<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\Id\TokenId;
use Doctrine\Common\Collections\ArrayCollection;

interface InvitationTokenRepositoryInterface
{
    public function save(InvitationToken $token): void;

    public function findById(TokenId $id): ?InvitationToken;

    public function findByToken(string $token): ?InvitationToken;

    public function findValidByEmail(Email $email): ?InvitationToken;

    /**
     * @return ArrayCollection<int, InvitationToken>
     */
    public function findPendingInvitations(): ArrayCollection;

    public function delete(InvitationToken $token): void;

    public function deleteExpired(): int;
}
