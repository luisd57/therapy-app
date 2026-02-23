<?php

declare(strict_types=1);

namespace App\Domain\User\Service;

use App\Domain\User\ValueObject\Email;

interface EmailSenderInterface
{
    public function sendInvitation(Email $to, string $patientName, string $registrationUrl): void;

    public function sendPasswordReset(Email $to, string $resetUrl): void;

    public function sendWelcome(Email $to, string $userName): void;
}
