<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Repository;

use App\Domain\Appointment\Entity\SlotLock;
use DateTimeImmutable;

interface SlotLockRepositoryInterface
{
    public function save(SlotLock $lock): void;

    public function findActiveByTimeSlot(
        DateTimeImmutable $slotStart,
        DateTimeImmutable $slotEnd,
    ): ?SlotLock;

    public function findByLockToken(string $lockToken): ?SlotLock;

    public function delete(SlotLock $lock): void;

    public function deleteExpired(): int;
}
