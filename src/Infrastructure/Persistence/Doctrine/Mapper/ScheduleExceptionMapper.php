<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\ValueObject\ExceptionId;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Doctrine\Entity\ScheduleExceptionEntity;

final class ScheduleExceptionMapper
{
    public static function toDomain(ScheduleExceptionEntity $entity): ScheduleException
    {
        return ScheduleException::reconstitute(
            id: ExceptionId::fromString($entity->getId()),
            therapistId: UserId::fromString($entity->getTherapistId()),
            startDateTime: $entity->getStartDateTime(),
            endDateTime: $entity->getEndDateTime(),
            reason: $entity->getReason(),
            isAllDay: $entity->isAllDay(),
            createdAt: $entity->getCreatedAt(),
        );
    }

    public static function toEntity(ScheduleException $exception, ?ScheduleExceptionEntity $entity = null): ScheduleExceptionEntity
    {
        if ($entity === null) {
            $entity = new ScheduleExceptionEntity();
        }

        $entity->setId($exception->getId()->getValue());
        $entity->setTherapistId($exception->getTherapistId()->getValue());
        $entity->setStartDateTime($exception->getStartDateTime());
        $entity->setEndDateTime($exception->getEndDateTime());
        $entity->setReason($exception->getReason());
        $entity->setIsAllDay($exception->isAllDay());
        $entity->setCreatedAt($exception->getCreatedAt());

        return $entity;
    }
}
