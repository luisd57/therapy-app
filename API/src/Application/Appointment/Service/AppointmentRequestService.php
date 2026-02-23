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
use App\Domain\Appointment\ValueObject\AppointmentId;
use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\ValueObject\UserId;
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
        private int $appointmentDurationMinutes,
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
    ): AppointmentOutputDTO {
        $startTime = new DateTimeImmutable($slotStartTime);
        $appointmentModality = AppointmentModality::from($modality);
        $emailVO = Email::fromString($email);
        $phoneVO = Phone::fromString($phone);
        $timeSlot = TimeSlot::create($startTime, $this->appointmentDurationMinutes);

        // If a lock token is provided, validate and consume it
        if ($lockToken !== null) {
            $lock = $this->slotLockRepository->findByLockToken($lockToken);

            if ($lock === null || $lock->isExpired()) {
                throw new InvalidLockTokenException();
            }

            $this->slotLockRepository->delete($lock);
        }

        // Verify the slot is still actually available.
        //
        // DESIGN NOTE: Locking is optional (?lockToken). Two users CAN submit
        // REQUESTED appointments for the same slot. This is intentional — the
        // therapist resolves conflicts manually during confirmation. The
        // availability check below only verifies the slot falls within a valid
        // schedule block and is not already CONFIRMED. Multiple REQUESTED
        // appointments for the same slot are permitted by design.
        $this->verifySlotAvailable($startTime, $appointmentModality);

        $patientUserId = $patientId !== null ? UserId::fromString($patientId) : null;

        $appointment = Appointment::request(
            id: AppointmentId::generate(),
            timeSlot: $timeSlot,
            modality: $appointmentModality,
            fullName: $fullName,
            email: $emailVO,
            phone: $phoneVO,
            city: $city,
            country: $country,
            patientId: $patientUserId,
        );

        $this->appointmentRepository->save($appointment);

        // Send acknowledgment to requester
        $this->emailSender->sendRequestAcknowledgment(
            to: $emailVO,
            fullName: $fullName,
            appointmentTime: $startTime,
            modality: $appointmentModality,
        );

        // Notify therapist
        $therapist = $this->userRepository->findSingleTherapist();
        $this->emailSender->sendNewRequestAlertToTherapist(
            therapistEmail: $therapist->getEmail(),
            requesterName: $fullName,
            appointmentTime: $startTime,
            modality: $appointmentModality,
        );

        return AppointmentOutputDTO::fromEntity($appointment);
    }

    private function verifySlotAvailable(
        DateTimeImmutable $startTime,
        AppointmentModality $appointmentModality,
    ): void {
        $therapist = $this->userRepository->findSingleTherapist();
        $dayStart = $startTime->setTime(0, 0);
        $dayEnd = $startTime->setTime(23, 59, 59);

        $schedules = $this->scheduleRepository->findActiveByTherapist($therapist->getId());
        $exceptions = $this->exceptionRepository->findByTherapistAndDateRange(
            $therapist->getId(),
            $dayStart,
            $dayEnd,
        );
        $blockingAppointments = $this->appointmentRepository->findBlockingByDateRange(
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
            context: $context,
            from: $dayStart,
            to: $dayEnd,
            slotDurationMinutes: $this->appointmentDurationMinutes,
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
