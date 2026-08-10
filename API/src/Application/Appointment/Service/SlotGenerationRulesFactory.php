<?php

declare(strict_types=1);

namespace App\Application\Appointment\Service;

use App\Domain\Appointment\Service\PracticeTimezoneProviderInterface;
use App\Domain\Appointment\Service\SlotGenerationRules;

/**
 * Assembles the configured slot rules so the handlers that compute availability
 * take one dependency instead of three.
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
            durationMinutes: $this->appointmentDurationMinutes,
            practiceTimeZone: $this->practiceTimezoneProvider->getTimeZone(),
            startIncrementMinutes: $this->slotStartIncrementMinutes,
        );
    }
}
