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
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\Clock\ClockInterface;
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
        private ClockInterface $clock,
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

        $today = $this->clock->now()->setTime(0, 0);

        // Batch load exceptions and appointments for the full range 
        $rangeEnd = $today->modify("+{$this->maxLookaheadWeeks} weeks")->modify('-1 day 23:59:59');

        $allExceptions = $this->exceptionRepository->findByTherapistAndDateRange(
            $therapistId,
            $today,
            $rangeEnd,
        );
        $allConfirmedAppointments = $this->appointmentRepository->findConfirmedByDateRange(
            $today,
            $rangeEnd,
        );

        for ($week = 0; $week < $this->maxLookaheadWeeks; $week++) {
            $weekStart = $today->modify("+{$week} weeks");
            $weekEnd = $weekStart->modify('+6 days 23:59:59');

            $weekExceptions = $allExceptions->filter(
                fn (ScheduleException $exception) =>
                    $exception->getStartDateTime() < $weekEnd
                    && $exception->getEndDateTime() > $weekStart,
            );
            $weekAppointments = $allConfirmedAppointments->filter(
                fn (Appointment $appointment) =>
                    $appointment->getTimeSlot()->getStartTime() < $weekEnd
                    && $appointment->getTimeSlot()->getEndTime() > $weekStart,
            );

            $context = new AvailabilityContext(
                schedules: $schedules,
                exceptions: $weekExceptions,
                blockingAppointments: $weekAppointments,
                activeLocks: new ArrayCollection(),
            );

            $availableSlots = $this->availabilityComputer->computeAvailableSlots(
                context: $context,
                from: $weekStart,
                to: $weekEnd,
                slotDurationMinutes: $this->appointmentDurationMinutes,
                now: $this->clock->now(),
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
