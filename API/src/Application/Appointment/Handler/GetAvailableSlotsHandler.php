<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\GetAvailableSlotsInputDTO;
use App\Application\Appointment\DTO\Output\AvailableSlotsOutputDTO;
use App\Application\Appointment\DTO\Output\TimeSlotOutputDTO;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Application\Appointment\Service\SlotGenerationRulesFactory;
use App\Domain\Appointment\Service\AvailabilityComputerInterface;
use App\Domain\Appointment\Service\AvailabilityContext;
use App\Domain\Appointment\Service\PracticeTimezoneProviderInterface;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\Clock\ClockInterface;
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
        private SlotGenerationRulesFactory $slotGenerationRulesFactory,
        private PracticeTimezoneProviderInterface $practiceTimezoneProvider,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetAvailableSlotsInputDTO $dto): AvailableSlotsOutputDTO
    {
        $therapist = $this->userRepository->findSingleTherapist();
        $therapistId = $therapist->getId();

        $practiceTimeZone = $this->practiceTimezoneProvider->getTimeZone();

        // The requested dates are calendar days in the practice zone, so the
        // window runs from local midnight to local midnight of the day after.
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $dto->from, $practiceTimeZone);
        $to = DateTimeImmutable::createFromFormat('!Y-m-d', $dto->to, $practiceTimeZone)->modify('+1 day');

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
            availabilityContext: $context,
            slotGenerationRules: $this->slotGenerationRulesFactory->create(),
            from: $from,
            to: $to,
            now: $this->clock->now(),
            modalityFilter: $modalityFilter,
        );

        $slotsByDate = [];
        foreach ($availableSlots as $slot) {
            // Slots are UTC instants; group them by the practice-local calendar
            // day so a late-evening slot does not land on the following date.
            $date = $slot->getStartTime()->setTimezone($practiceTimeZone)->format('Y-m-d');
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
