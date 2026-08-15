<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\SendDailyAgendaInputDTO;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Service\AppointmentEmailSenderInterface;
use App\Domain\Appointment\Service\PracticeTimezoneProviderInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use DateTimeImmutable;

final readonly class SendDailyAgendaHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AppointmentRepositoryInterface $appointmentRepository,
        private AppointmentEmailSenderInterface $emailSender,
        private PracticeTimezoneProviderInterface $practiceTimezoneProvider,
    ) {
    }

    public function __invoke(SendDailyAgendaInputDTO $dto): int
    {
        // The agenda covers the therapist's calendar day, so the date names a day in her zone.
        $date = new DateTimeImmutable($dto->date, $this->practiceTimezoneProvider->getTimeZone());
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
