<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\Appointment;

/** What one email states about time: the reader's own, and the other party's when it differs. */
final readonly class RecipientTimes
{
    public function __construct(
        public RenderedTime $recipient,
        public ?string $otherPartyLine,
    ) {
    }
}
