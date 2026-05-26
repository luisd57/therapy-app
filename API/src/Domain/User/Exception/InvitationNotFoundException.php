<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\Exception\DomainException;

final class InvitationNotFoundException extends DomainException
{
    public function __construct(string $identifier)
    {
        parent::__construct(
            message: "Invitation not found: {$identifier}",
            errorCode: 'INVITATION_NOT_FOUND',
        );
    }
}
