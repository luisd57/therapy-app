<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use App\Domain\Appointment\Service\PracticeTimezoneProviderInterface;
use App\Domain\User\ValueObject\Timezone;
use DateTimeZone;

/**
 * Reads the practice zone from PRACTICE_TIMEZONE.
 *
 * Configuration rather than a database column: there is one therapist and she is
 * not relocating. Should that change, this is the only class that has to.
 */
final class EnvPracticeTimezoneProvider implements PracticeTimezoneProviderInterface
{
    private readonly Timezone $timezone;
    private readonly DateTimeZone $dateTimeZone;

    public function __construct(string $practiceTimezone)
    {
        // Validating here means a typo fails at container boot rather than on the
        // first slot request.
        $this->timezone = Timezone::fromString($practiceTimezone);
        $this->dateTimeZone = $this->timezone->toDateTimeZone();
    }

    public function getTimeZone(): DateTimeZone
    {
        return $this->dateTimeZone;
    }

    public function getIdentifier(): string
    {
        return $this->timezone->getValue();
    }
}
