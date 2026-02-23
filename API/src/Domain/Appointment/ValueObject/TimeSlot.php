<?php

declare(strict_types=1);

namespace App\Domain\Appointment\ValueObject;

use DateTimeImmutable;

final readonly class TimeSlot
{
    private function __construct(
        private DateTimeImmutable $startTime,
        private DateTimeImmutable $endTime,
    ) {
    }

    public static function create(DateTimeImmutable $startTime, int $durationMinutes): self
    {
        if ($durationMinutes <= 0) {
            throw new \InvalidArgumentException('Duration must be positive.');
        }

        $endTime = $startTime->modify("+{$durationMinutes} minutes");

        return new self($startTime, $endTime);
    }

    public static function fromStartEnd(DateTimeImmutable $start, DateTimeImmutable $end): self
    {
        if ($end <= $start) {
            throw new \InvalidArgumentException('End time must be after start time.');
        }

        return new self($start, $end);
    }

    public function getStartTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndTime(): DateTimeImmutable
    {
        return $this->endTime;
    }

    public function getDurationMinutes(): int
    {
        return (int) (($this->endTime->getTimestamp() - $this->startTime->getTimestamp()) / 60);
    }

    public function overlaps(self $other): bool
    {
        return $this->startTime < $other->endTime && $other->startTime < $this->endTime;
    }

    public function contains(DateTimeImmutable $dateTime): bool
    {
        return $dateTime >= $this->startTime && $dateTime < $this->endTime;
    }

    public function equals(self $other): bool
    {
        return $this->startTime == $other->startTime && $this->endTime == $other->endTime;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s',
            $this->startTime->format('Y-m-d H:i'),
            $this->endTime->format('H:i'),
        );
    }
}
