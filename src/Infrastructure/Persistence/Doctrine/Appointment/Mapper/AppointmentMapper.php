<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Appointment\Mapper;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\ValueObject\AppointmentId;
use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\Appointment\ValueObject\AppointmentStatus;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Doctrine\Appointment\Entity\AppointmentEntity;

final class AppointmentMapper
{
    public static function toDomain(AppointmentEntity $entity): Appointment
    {
        $patientId = $entity->getPatientId() !== null
            ? UserId::fromString($entity->getPatientId())
            : null;

        return Appointment::reconstitute(
            id: AppointmentId::fromString($entity->getId()),
            timeSlot: TimeSlot::fromStartEnd($entity->getStartTime(), $entity->getEndTime()),
            modality: AppointmentModality::from($entity->getModality()),
            status: AppointmentStatus::from($entity->getStatus()),
            fullName: $entity->getFullName(),
            email: Email::fromString($entity->getEmail()),
            phone: Phone::fromString($entity->getPhone()),
            city: $entity->getCity(),
            country: $entity->getCountry(),
            patientId: $patientId,
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            paymentVerified: $entity->isPaymentVerified(),
        );
    }

    public static function toEntity(Appointment $appointment, ?AppointmentEntity $entity = null): AppointmentEntity
    {
        if ($entity === null) {
            $entity = new AppointmentEntity();
        }

        $entity->setId($appointment->getId()->getValue());
        $entity->setStartTime($appointment->getTimeSlot()->getStartTime());
        $entity->setEndTime($appointment->getTimeSlot()->getEndTime());
        $entity->setModality($appointment->getModality()->value);
        $entity->setStatus($appointment->getStatus()->value);
        $entity->setFullName($appointment->getFullName());
        $entity->setEmail($appointment->getEmail()->getValue());
        $entity->setPhone($appointment->getPhone()->getValue());
        $entity->setCity($appointment->getCity());
        $entity->setCountry($appointment->getCountry());
        $entity->setPatientId($appointment->getPatientId()?->getValue());
        $entity->setCreatedAt($appointment->getCreatedAt());
        $entity->setUpdatedAt($appointment->getUpdatedAt());
        $entity->setPaymentVerified($appointment->isPaymentVerified());

        return $entity;
    }
}
