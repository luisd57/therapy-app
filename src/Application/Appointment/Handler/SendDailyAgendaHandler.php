<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\SendDailyAgendaInputDTO;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Service\AppointmentEmailSenderInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use DateTimeImmutable;

final readonly class SendDailyAgendaHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AppointmentRepositoryInterface $appointmentRepository,
        private AppointmentEmailSenderInterface $emailSender,
    ) {
    }

    public function __invoke(SendDailyAgendaInputDTO $dto): int
    {
        $date = new DateTimeImmutable($dto->date);
        $therapist = $this->userRepository->findSingleTherapist();
        $appointments = $this->appointmentRepository->findConfirmedByDate($date);

        $this->emailSender->sendDailyAgendaToTherapist(
            therapistEmail: $therapist->getEmail(),
            therapistName: $therapist->getFullName(),
            date: $date,
            appointments: $appointments,
        );

        return $appointments->count();
    }
}
