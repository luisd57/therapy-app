<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Appointment\Mapper;

use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\Appointment\ValueObject\SlotLockId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Infrastructure\Persistence\Doctrine\Appointment\Entity\SlotLockEntity;

final class SlotLockMapper
{
    public static function toDomain(SlotLockEntity $entity): SlotLock
    {
        return SlotLock::reconstitute(
            id: SlotLockId::fromString($entity->getId()),
            timeSlot: TimeSlot::fromStartEnd($entity->getStartTime(), $entity->getEndTime()),
            modality: AppointmentModality::from($entity->getModality()),
            lockToken: $entity->getLockToken(),
            createdAt: $entity->getCreatedAt(),
            expiresAt: $entity->getExpiresAt(),
        );
    }

    public static function toEntity(SlotLock $slotLock, ?SlotLockEntity $entity = null): SlotLockEntity
    {
        if ($entity === null) {
            $entity = new SlotLockEntity();
        }

        $entity->setId($slotLock->getId()->getValue());
        $entity->setStartTime($slotLock->getTimeSlot()->getStartTime());
        $entity->setEndTime($slotLock->getTimeSlot()->getEndTime());
        $entity->setModality($slotLock->getModality()->value);
        $entity->setLockToken(hash('sha256', $slotLock->getLockToken()));
        $entity->setCreatedAt($slotLock->getCreatedAt());
        $entity->setExpiresAt($slotLock->getExpiresAt());

        return $entity;
    }
}
