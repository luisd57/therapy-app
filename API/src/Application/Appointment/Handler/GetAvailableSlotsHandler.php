<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\GetAvailableSlotsInputDTO;
use App\Application\Appointment\DTO\Output\AvailableSlotsOutputDTO;
use App\Application\Appointment\DTO\Output\TimeSlotOutputDTO;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Service\AvailabilityComputerInterface;
use App\Domain\Appointment\Service\AvailabilityContext;
use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\User\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class GetAvailableSlotsHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TherapistScheduleRepositoryInterface $scheduleRepository,
        private ScheduleExceptionRepositoryInterface $exceptionRepository,
        private AppointmentRepositoryInterface $appointmentRepository,
        private AvailabilityComputerInterface $availabilityComputer,
        private int $appointmentDurationMinutes,
    ) {
    }

    public function __invoke(GetAvailableSlotsInputDTO $dto): AvailableSlotsOutputDTO
    {
        $therapist = $this->userRepository->findSingleTherapist();
        $therapistId = $therapist->getId();

        $from = new DateTimeImmutable($dto->from);
        $to = new DateTimeImmutable($dto->to . ' 23:59:59');
        $modalityFilter = $dto->modality !== null
            ? AppointmentModality::from($dto->modality)
            : null;

        $schedules = $this->scheduleRepository->findActiveByTherapist($therapistId);
        $exceptions = $this->exceptionRepository->findByTherapistAndDateRange(
            $therapistId,
            $from,
            $to,
        );
        $confirmedAppointments = $this->appointmentRepository->findConfirmedByDateRange($from, $to);

        $context = new AvailabilityContext(
            schedules: $schedules,
            exceptions: $exceptions,
            blockingAppointments: $confirmedAppointments,
            activeLocks: new ArrayCollection(),
        );

        $availableSlots = $this->availabilityComputer->computeAvailableSlots(
            context: $context,
            from: $from,
            to: $to,
            slotDurationMinutes: $this->appointmentDurationMinutes,
            modalityFilter: $modalityFilter,
        );

        $slotsByDate = [];
        foreach ($availableSlots as $slot) {
            $date = $slot->getStartTime()->format('Y-m-d');
            $slotsByDate[$date][] = TimeSlotOutputDTO::fromValueObject($slot);
        }

        return new AvailableSlotsOutputDTO(
            from: $dto->from,
            to: $dto->to,
            modality: $dto->modality,
            slotsByDate: $slotsByDate,
            totalSlots: $availableSlots->count(),
        );
    }
}
