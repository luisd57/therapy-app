<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Domain\User\ValueObject\Timezone;

trait ValidationHelperTrait
{
    /**
     * A bare calendar day, only for therapist-facing ranges read in the practice zone.
     * Anything naming a moment must use isValidInstant() instead.
     */
    protected function isValidDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /**
     * An ISO-8601 instant that carries its own UTC offset, either 'Z' or ±HH:MM.
     * Offset-less input is rejected, not guessed: picking a zone silently is the bug to avoid.
     */
    protected function isValidInstant(string $instant): bool
    {
        $pattern = '/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(?:\.\d+)?(Z|[+-]\d{2}:\d{2})$/';

        if (preg_match($pattern, $instant, $matches) !== 1) {
            return false;
        }

        $offset = $matches[3] === 'Z' ? '+00:00' : $matches[3];
        $normalized = $matches[1] . 'T' . $matches[2] . $offset;

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $normalized);

        // Round-trip guard: createFromFormat rolls impossible dates forward
        // rather than failing, so 2026-02-31 would otherwise become 2026-03-03.
        return $parsed !== false && $parsed->format('Y-m-d\TH:i:sP') === $normalized;
    }

    /**
     * Delegates to the value object so there is one definition of a valid zone.
     * Notably rejects fixed offsets like '-04:00', which carry no DST rules.
     */
    protected function isValidTimezone(string $timezone): bool
    {
        try {
            Timezone::fromString($timezone);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    protected function isValidModality(string $modality): bool
    {
        return in_array($modality, ['ONLINE', 'IN_PERSON'], true);
    }
}
