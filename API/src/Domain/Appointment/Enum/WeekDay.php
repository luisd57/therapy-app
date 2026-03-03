<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Enum;

enum WeekDay: int
{
    case MONDAY = 1;
    case TUESDAY = 2;
    case WEDNESDAY = 3;
    case THURSDAY = 4;
    case FRIDAY = 5;
    case SATURDAY = 6;
    case SUNDAY = 7;

    public static function fromDateTimeImmutable(\DateTimeImmutable $date): self
    {
        return self::from((int) $date->format('N'));
    }

    public function getDisplayName(): string
    {
        return match ($this) {
            self::MONDAY => 'Monday',
            self::TUESDAY => 'Tuesday',
            self::WEDNESDAY => 'Wednesday',
            self::THURSDAY => 'Thursday',
            self::FRIDAY => 'Friday',
            self::SATURDAY => 'Saturday',
            self::SUNDAY => 'Sunday',
        };
    }
}
