<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\PatientRequestAppointmentInputDTO;
use App\Application\Appointment\DTO\Output\AppointmentOutputDTO;
use App\Application\Appointment\Service\AppointmentRequestServiceInterface;
use App\Domain\User\Exception\IncompleteProfileException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;

final readonly class PatientRequestAppointmentHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AppointmentRequestServiceInterface $appointmentRequestService,
    ) {
    }

    public function __invoke(PatientRequestAppointmentInputDTO $dto): AppointmentOutputDTO
    {
        $patient = $this->userRepository->findById(UserId::fromString($dto->patientId));

        if ($patient === null) {
            throw new UserNotFoundException($dto->patientId);
        }

        if ($patient->getPhone() === null || $patient->getAddress() === null) {
            throw new IncompleteProfileException();
        }

        return $this->appointmentRequestService->requestAppointment(
            slotStartTime: $dto->slotStartTime,
            modality: $dto->modality,
            fullName: $patient->getFullName(),
            phone: $patient->getPhone()->getValue(),
            email: $patient->getEmail()->getValue(),
            city: $patient->getAddress()->getCity(),
            country: $patient->getAddress()->getCountry(),
            lockToken: $dto->lockToken,
            patientId: $dto->patientId,
        );
    }
}
