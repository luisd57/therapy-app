<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use DateTimeZone;

/**
 * An IANA timezone identifier, such as 'America/Caracas' or 'Europe/Madrid'.
 * Never a fixed offset: only a named zone carries the DST rules. See ADR 0001.
 */
final readonly class Timezone
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new \InvalidArgumentException('Timezone cannot be empty.');
        }

        // new DateTimeZone('-04:00') succeeds, so constructing one is not a
        // validity check. Membership in the identifier list is what rejects
        // fixed offsets. ALL_WITH_BC keeps legacy aliases ('Asia/Calcutta')
        // working, since browsers still report some of them.
        if (!in_array($normalized, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not an IANA timezone identifier.', $normalized),
            );
        }

        return new self($normalized);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function toDateTimeZone(): DateTimeZone
    {
        return new DateTimeZone($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
