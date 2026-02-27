<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\GetNextAvailableWeekInputDTO;
use App\Application\Appointment\DTO\Output\NextAvailableWeekOutputDTO;
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

final readonly class GetNextAvailableWeekHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TherapistScheduleRepositoryInterface $scheduleRepository,
        private ScheduleExceptionRepositoryInterface $exceptionRepository,
        private AppointmentRepositoryInterface $appointmentRepository,
        private AvailabilityComputerInterface $availabilityComputer,
        private int $appointmentDurationMinutes,
        private int $maxLookaheadWeeks,
    ) {
    }

    public function __invoke(GetNextAvailableWeekInputDTO $dto): NextAvailableWeekOutputDTO
    {
        $therapist = $this->userRepository->findSingleTherapist();
        $therapistId = $therapist->getId();

        $modalityFilter = $dto->modality !== null
            ? AppointmentModality::from($dto->modality)
            : null;

        // Schedules are static — load once and reuse across all weeks
        $schedules = $this->scheduleRepository->findActiveByTherapist($therapistId);

        $today = new DateTimeImmutable('today');

        for ($week = 0; $week < $this->maxLookaheadWeeks; $week++) {
            $weekStart = $today->modify("+{$week} weeks");
            $weekEnd = $weekStart->modify('+6 days 23:59:59');

            $exceptions = $this->exceptionRepository->findByTherapistAndDateRange(
                $therapistId,
                $weekStart,
                $weekEnd,
            );
            $confirmedAppointments = $this->appointmentRepository->findConfirmedByDateRange(
                $weekStart,
                $weekEnd,
            );

            $context = new AvailabilityContext(
                schedules: $schedules,
                exceptions: $exceptions,
                blockingAppointments: $confirmedAppointments,
                activeLocks: new ArrayCollection(),
            );

            $availableSlots = $this->availabilityComputer->computeAvailableSlots(
                context: $context,
                from: $weekStart,
                to: $weekEnd,
                slotDurationMinutes: $this->appointmentDurationMinutes,
                modalityFilter: $modalityFilter,
            );

            if ($availableSlots->count() > 0) {
                $slotsByDate = [];
                foreach ($availableSlots as $slot) {
                    $date = $slot->getStartTime()->format('Y-m-d');
                    $slotsByDate[$date][] = TimeSlotOutputDTO::fromValueObject($slot);
                }

                return new NextAvailableWeekOutputDTO(
                    found: true,
                    weekStart: $weekStart->format('Y-m-d'),
                    weekEnd: $weekStart->modify('+6 days')->format('Y-m-d'),
                    modality: $dto->modality,
                    slotsByDate: $slotsByDate,
                    totalSlots: $availableSlots->count(),
                );
            }
        }

        return new NextAvailableWeekOutputDTO(
            found: false,
            weekStart: null,
            weekEnd: null,
            modality: $dto->modality,
            slotsByDate: [],
            totalSlots: 0,
        );
    }
}
