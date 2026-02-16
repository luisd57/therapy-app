<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Appointment\Mapper;

use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\ValueObject\ScheduleId;
use App\Domain\Appointment\ValueObject\WeekDay;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Doctrine\Appointment\Entity\TherapistScheduleEntity;

final class TherapistScheduleMapper
{
    public static function toDomain(TherapistScheduleEntity $entity): TherapistSchedule
    {
        return TherapistSchedule::reconstitute(
            id: ScheduleId::fromString($entity->getId()),
            therapistId: UserId::fromString($entity->getTherapistId()),
            dayOfWeek: WeekDay::from($entity->getDayOfWeek()),
            startTime: $entity->getStartTime(),
            endTime: $entity->getEndTime(),
            supportsOnline: $entity->isSupportsOnline(),
            supportsInPerson: $entity->isSupportsInPerson(),
            isActive: $entity->isActive(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public static function toEntity(TherapistSchedule $schedule, ?TherapistScheduleEntity $entity = null): TherapistScheduleEntity
    {
        if ($entity === null) {
            $entity = new TherapistScheduleEntity();
        }

        $entity->setId($schedule->getId()->getValue());
        $entity->setTherapistId($schedule->getTherapistId()->getValue());
        $entity->setDayOfWeek($schedule->getDayOfWeek()->value);
        $entity->setStartTime($schedule->getStartTime());
        $entity->setEndTime($schedule->getEndTime());
        $entity->setSupportsOnline($schedule->isSupportsOnline());
        $entity->setSupportsInPerson($schedule->isSupportsInPerson());
        $entity->setIsActive($schedule->isActive());
        $entity->setCreatedAt($schedule->getCreatedAt());
        $entity->setUpdatedAt($schedule->getUpdatedAt());

        return $entity;
    }
}
