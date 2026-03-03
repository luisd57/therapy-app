<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\BookAppointmentInputDTO;
use App\Application\Appointment\DTO\Output\AppointmentOutputDTO;
use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Id\AppointmentId;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\Id\UserId;
use DateTimeImmutable;

final readonly class BookAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private int $appointmentDurationMinutes,
    ) {
    }

    public function __invoke(BookAppointmentInputDTO $dto): AppointmentOutputDTO
    {
        $startTime = new DateTimeImmutable($dto->slotStartTime);
        $timeSlot = TimeSlot::create($startTime, $this->appointmentDurationMinutes);
        $modality = AppointmentModality::from($dto->modality);
        $email = Email::fromString($dto->email);
        $phone = Phone::fromString($dto->phone);
        $patientId = $dto->patientId !== null ? UserId::fromString($dto->patientId) : null;

        $appointment = Appointment::book(
            id: AppointmentId::generate(),
            timeSlot: $timeSlot,
            modality: $modality,
            fullName: $dto->fullName,
            email: $email,
            phone: $phone,
            city: $dto->city,
            country: $dto->country,
            patientId: $patientId,
        );

        $this->appointmentRepository->save($appointment);

        return AppointmentOutputDTO::fromEntity($appointment);
    }
}
