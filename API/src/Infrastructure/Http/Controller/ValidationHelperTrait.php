<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

trait ValidationHelperTrait
{
    protected function isValidDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    protected function isValidDateTime(string $dateTime): bool
    {
        return \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $dateTime) !== false
            || \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dateTime) !== false;
    }

    protected function isValidModality(string $modality): bool
    {
        return in_array($modality, ['ONLINE', 'IN_PERSON'], true);
    }
}
