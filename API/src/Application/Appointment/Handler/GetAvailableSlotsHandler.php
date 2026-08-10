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
use App\Application\Shared\InstantFormatter;
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

        // The window is a pair of instants chosen by the client, so its own week
        // boundaries are honoured rather than being snapped to practice days.
        $from = new DateTimeImmutable($dto->from);
        $to = new DateTimeImmutable($dto->to);

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

        return new AvailableSlotsOutputDTO(
            from: InstantFormatter::toAtomUtc($from),
            to: InstantFormatter::toAtomUtc($to),
            modality: $dto->modality,
            practiceTimezone: $this->practiceTimezoneProvider->getIdentifier(),
            slots: $availableSlots->map(
                fn ($timeSlot) => TimeSlotOutputDTO::fromValueObject($timeSlot),
            ),
        );
    }
}
