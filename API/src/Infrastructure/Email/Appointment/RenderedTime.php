<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\Appointment;

use DateTimeImmutable;
use DateTimeZone;

/**
 * An instant formatted for one reader, with the zone it was rendered in named.
 * An email is read outside the app, so a bare time carries no zone context. See ADR-0005.
 */
final readonly class RenderedTime
{
    private function __construct(
        public string $date,
        public string $time,
        public string $zoneLabel,
    ) {
    }

    public static function in(DateTimeImmutable $instant, DateTimeZone $timeZone): self
    {
        $local = $instant->setTimezone($timeZone);

        return new self(
            $local->format('l, F j, Y'),
            $local->format('g:i A'),
            self::zoneLabelFor($timeZone),
        );
    }

    // The city is the part a reader recognises; the region prefix adds nothing.
    public static function zoneLabelFor(DateTimeZone $timeZone): string
    {
        $parts = explode('/', $timeZone->getName());

        return str_replace('_', ' ', (string) end($parts));
    }

    /** Time with its zone named, e.g. "4:30 PM (Madrid)". */
    public function timeWithZone(): string
    {
        return "{$this->time} ({$this->zoneLabel})";
    }

    /** Date and time on one line, for the other party's secondary line. */
    public function full(): string
    {
        return "{$this->date} at {$this->timeWithZone()}";
    }
}
