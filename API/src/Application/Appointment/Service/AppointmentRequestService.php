<?php

declare(strict_types=1);

namespace App\Application\Appointment\Service;

use App\Application\Appointment\DTO\Output\AppointmentOutputDTO;
use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Exception\InvalidLockTokenException;
use App\Domain\Appointment\Exception\SlotNotAvailableException;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\Repository\SlotLockRepositoryInterface;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Service\AppointmentEmailSenderInterface;
use App\Domain\Appointment\Service\AvailabilityComputerInterface;
use App\Domain\Appointment\Service\AvailabilityContext;
use App\Domain\Appointment\Service\PracticeTimezoneProviderInterface;
use App\Domain\Appointment\Id\AppointmentId;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\ValueObject\Timezone;
use App\Domain\User\Id\UserId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class AppointmentRequestService implements AppointmentRequestServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AppointmentRepositoryInterface $appointmentRepository,
        private SlotLockRepositoryInterface $slotLockRepository,
        private TherapistScheduleRepositoryInterface $scheduleRepository,
        private ScheduleExceptionRepositoryInterface $exceptionRepository,
        private AvailabilityComputerInterface $availabilityComputer,
        private AppointmentEmailSenderInterface $emailSender,
        private SlotGenerationRulesFactory $slotGenerationRulesFactory,
        private PracticeTimezoneProviderInterface $practiceTimezoneProvider,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function requestAppointment(
        string $slotStartTime,
        string $modality,
        string $fullName,
        string $phone,
        string $email,
        string $city,
        string $country,
        ?string $lockToken = null,
        ?string $patientId = null,
        ?string $requesterTimezone = null,
    ): AppointmentOutputDTO {
        $now = $this->clock->now();
        $startTime = new DateTimeImmutable($slotStartTime);
        $appointmentModality = AppointmentModality::from($modality);
        $emailVO = Email::fromString($email);
        $phoneVO = Phone::fromString($phone);
        $timeSlot = TimeSlot::create($startTime, $this->slotGenerationRulesFactory->create()->durationMinutes);

        // If a lock token is provided, validate and consume it
        if ($lockToken !== null) {
            $lock = $this->slotLockRepository->findByLockToken($lockToken);

            if ($lock === null || $lock->isExpired($now)) {
                throw new InvalidLockTokenException();
            }

            // Verify the lock matches the slot being booked
            if ($lock->getTimeSlot()->getStartTime() != $startTime
                || $lock->getModality() !== $appointmentModality) {
                throw new InvalidLockTokenException();
            }

            $this->slotLockRepository->delete($lock);
        }

        // Only CONFIRMED appointments block new requests. Several REQUESTED ones for the
        // same slot are allowed by design; the therapist resolves those by hand.
        $this->verifySlotAvailable($startTime, $appointmentModality);

        $patientUserId = $patientId !== null ? UserId::fromString($patientId) : null;

        $requesterTimezoneVO = $requesterTimezone !== null
            ? Timezone::fromString($requesterTimezone)
            : null;

        $appointment = Appointment::request(
            id: AppointmentId::generate(),
            timeSlot: $timeSlot,
            modality: $appointmentModality,
            fullName: $fullName,
            email: $emailVO,
            phone: $phoneVO,
            city: $city,
            country: $country,
            now: $now,
            patientId: $patientUserId,
            requesterTimezone: $requesterTimezoneVO,
        );

        $this->appointmentRepository->save($appointment);

        try {
            $this->emailSender->sendRequestAcknowledgment(
                to: $emailVO,
                fullName: $fullName,
                appointmentTime: $startTime,
                modality: $appointmentModality,
                requesterTimezone: $requesterTimezoneVO,
            );

            $therapist = $this->userRepository->findSingleTherapist();
            $this->emailSender->sendNewRequestAlertToTherapist(
                therapistEmail: $therapist->getEmail(),
                requesterName: $fullName,
                appointmentTime: $startTime,
                modality: $appointmentModality,
                requesterTimezone: $requesterTimezoneVO,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send appointment request email: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
                'email_type' => 'appointment_request',
            ]);
        }

        return AppointmentOutputDTO::fromEntity($appointment);
    }

    private function verifySlotAvailable(
        DateTimeImmutable $startTime,
        AppointmentModality $appointmentModality,
    ): void {
        $therapist = $this->userRepository->findSingleTherapist();

        // Bound the search by the therapist's calendar day, not the requester's:
        // a 20:00 Caracas slot is already tomorrow for a patient in Madrid.
        $practiceDay = $startTime->setTimezone($this->practiceTimezoneProvider->getTimeZone());
        $dayStart = $practiceDay->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        $schedules = $this->scheduleRepository->findActiveByTherapist($therapist->getId());
        $exceptions = $this->exceptionRepository->findByTherapistAndDateRange(
            $therapist->getId(),
            $dayStart,
            $dayEnd,
        );
        $blockingAppointments = $this->appointmentRepository->findConfirmedByDateRange(
            $dayStart,
            $dayEnd,
        );

        $context = new AvailabilityContext(
            schedules: $schedules,
            exceptions: $exceptions,
            blockingAppointments: $blockingAppointments,
            activeLocks: new ArrayCollection(),
        );

        $availableSlots = $this->availabilityComputer->computeAvailableSlots(
            availabilityContext: $context,
            slotGenerationRules: $this->slotGenerationRulesFactory->create(),
            from: $dayStart,
            to: $dayEnd,
            now: $this->clock->now(),
            modalityFilter: $appointmentModality,
        );

        $isAvailable = $availableSlots->exists(
            fn (int $_index, TimeSlot $timeSlot) => $timeSlot->getStartTime() == $startTime,
        );

        if (!$isAvailable) {
            throw new SlotNotAvailableException();
        }
    }
}
