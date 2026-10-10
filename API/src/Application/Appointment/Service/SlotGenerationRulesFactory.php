<?php

declare(strict_types=1);

namespace App\Application\Appointment\Service;

use App\Domain\Appointment\Service\PracticeTimezoneProviderInterface;
use App\Domain\Appointment\Service\SlotGenerationRules;

/**
 * Builds the configured slot rules. The only place the session duration is wired,
 * so anything that needs it takes this instead of the raw int.
 */
final readonly class SlotGenerationRulesFactory
{
    public function __construct(
        private PracticeTimezoneProviderInterface $practiceTimezoneProvider,
        private int $appointmentDurationMinutes,
        private int $slotStartIncrementMinutes,
    ) {
    }

    public function create(): SlotGenerationRules
    {
        return SlotGenerationRules::create(
            practiceTimeZone: $this->practiceTimezoneProvider->getTimeZone(),
            durationMinutes: $this->appointmentDurationMinutes,
            startIncrementMinutes: $this->slotStartIncrementMinutes,
        );
    }
}
