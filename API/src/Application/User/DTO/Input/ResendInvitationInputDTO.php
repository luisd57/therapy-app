<?php

declare(strict_types=1);

namespace App\Application\User\DTO\Input;

final readonly class ResendInvitationInputDTO
{
    public function __construct(
        public string $tokenId,
    ) {
    }
}
