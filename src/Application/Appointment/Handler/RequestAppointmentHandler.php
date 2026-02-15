<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\RequestAppointmentInputDTO;
use App\Application\Appointment\DTO\Output\AppointmentDTO;
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
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class RequestAppointmentHandler
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

    public function handle(RequestAppointmentInputDTO $input): AppointmentDTO
    {
        $startTime = new DateTimeImmutable($input->slotStartTime);
        $modality = AppointmentModality::from($input->modality);
        $email = Email::fromString($input->email);
        $phone = Phone::fromString($input->phone);
        $timeSlot = TimeSlot::create($startTime, $this->appointmentDurationMinutes);

        // If a lock token is provided, validate and consume it
        if ($input->lockToken !== null) {
            $lock = $this->slotLockRepository->findByLockToken($input->lockToken);

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
        $this->verifySlotAvailable($startTime, $modality);

        $appointment = Appointment::request(
            id: AppointmentId::generate(),
            timeSlot: $timeSlot,
            modality: $modality,
            fullName: $input->fullName,
            email: $email,
            phone: $phone,
            city: $input->city,
            country: $input->country,
        );

        $this->appointmentRepository->save($appointment);

        // Send acknowledgment to requester
        $this->emailSender->sendRequestAcknowledgment(
            to: $email,
            fullName: $input->fullName,
            appointmentTime: $startTime,
            modality: $modality,
        );

        // Notify therapist
        $therapist = $this->userRepository->findSingleTherapist();
        $this->emailSender->sendNewRequestAlertToTherapist(
            therapistEmail: $therapist->getEmail(),
            requesterName: $input->fullName,
            appointmentTime: $startTime,
            modality: $modality,
        );

        return AppointmentDTO::fromEntity($appointment);
    }

    private function verifySlotAvailable(
        DateTimeImmutable $startTime,
        AppointmentModality $modality,
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
            modalityFilter: $modality,
        );

        $isAvailable = $availableSlots->exists(
            fn (int $_, TimeSlot $s) => $s->getStartTime() == $startTime,
        );

        if (!$isAvailable) {
            throw new SlotNotAvailableException();
        }
    }
}
