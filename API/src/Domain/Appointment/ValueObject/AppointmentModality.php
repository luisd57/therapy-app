<?php

declare(strict_types=1);

namespace App\Domain\Appointment\ValueObject;

enum AppointmentModality: string
{
    case ONLINE = 'ONLINE';
    case IN_PERSON = 'IN_PERSON';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::ONLINE => 'Online',
            self::IN_PERSON => 'In-Person',
        };
    }
}
