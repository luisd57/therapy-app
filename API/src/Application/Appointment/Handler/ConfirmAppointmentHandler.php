<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\ConfirmAppointmentInputDTO;
use App\Application\Appointment\DTO\Output\AppointmentOutputDTO;
use App\Domain\Appointment\Exception\AppointmentNotFoundException;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Service\AppointmentEmailSenderInterface;
use App\Domain\Appointment\Id\AppointmentId;

final readonly class ConfirmAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private AppointmentEmailSenderInterface $emailSender,
    ) {
    }

    public function __invoke(ConfirmAppointmentInputDTO $dto): AppointmentOutputDTO
    {
        $appointment = $this->appointmentRepository->findById(
            AppointmentId::fromString($dto->appointmentId)
        );

        if ($appointment === null) {
            throw new AppointmentNotFoundException($dto->appointmentId);
        }

        $appointment->confirm();
        $this->appointmentRepository->save($appointment);

        $this->emailSender->sendConfirmationToPatient(
            to: $appointment->getEmail(),
            fullName: $appointment->getFullName(),
            appointmentTime: $appointment->getTimeSlot()->getStartTime(),
            modality: $appointment->getModality(),
        );

        return AppointmentOutputDTO::fromEntity($appointment);
    }
}
