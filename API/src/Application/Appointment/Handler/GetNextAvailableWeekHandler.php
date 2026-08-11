<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\GetNextAvailableWeekInputDTO;
use App\Application\Appointment\DTO\Output\NextAvailableWeekOutputDTO;
use App\Application\Appointment\DTO\Output\TimeSlotOutputDTO;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Application\Appointment\Service\SlotGenerationRulesFactory;
use App\Application\Shared\InstantFormatter;
use App\Domain\Appointment\Service\AvailabilityComputerInterface;
use App\Domain\Appointment\Service\AvailabilityContext;
use App\Domain\Appointment\Service\PracticeTimezoneProviderInterface;
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
        private SlotGenerationRulesFactory $slotGenerationRulesFactory,
        private PracticeTimezoneProviderInterface $practiceTimezoneProvider,
        private ClockInterface $clock,
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

        // Schedules are static, so load once and reuse across all weeks
        $schedules = $this->scheduleRepository->findActiveByTherapist($therapistId);

        $practiceTimeZone = $this->practiceTimezoneProvider->getTimeZone();

        // "Today" is the therapist's day, not the server's. At 02:00 UTC it is
        // still the previous day in Caracas, and the week must start there.
        $today = $this->clock->now()->setTimezone($practiceTimeZone)->setTime(0, 0);

        // Batch load exceptions and appointments for the full range (end exclusive)
        $rangeEnd = $today->modify("+{$this->maxLookaheadWeeks} weeks");

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
            $weekEnd = $weekStart->modify('+7 days');

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
                availabilityContext: $context,
                slotGenerationRules: $this->slotGenerationRulesFactory->create(),
                from: $weekStart,
                to: $weekEnd,
                now: $this->clock->now(),
                modalityFilter: $modalityFilter,
            );

            if ($availableSlots->count() > 0) {
                return new NextAvailableWeekOutputDTO(
                    found: true,
                    weekStart: InstantFormatter::toAtomUtc($weekStart),
                    weekEnd: InstantFormatter::toAtomUtc($weekEnd),
                    modality: $dto->modality,
                    practiceTimezone: $this->practiceTimezoneProvider->getIdentifier(),
                    slots: $availableSlots->map(
                        fn ($timeSlot) => TimeSlotOutputDTO::fromValueObject($timeSlot),
                    ),
                );
            }
        }

        return new NextAvailableWeekOutputDTO(
            found: false,
            weekStart: null,
            weekEnd: null,
            modality: $dto->modality,
            practiceTimezone: $this->practiceTimezoneProvider->getIdentifier(),
            slots: new ArrayCollection(),
        );
    }
}
