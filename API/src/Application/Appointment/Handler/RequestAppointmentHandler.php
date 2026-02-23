<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\RequestAppointmentInputDTO;
use App\Application\Appointment\DTO\Output\AppointmentOutputDTO;
use App\Application\Appointment\Service\AppointmentRequestServiceInterface;

final readonly class RequestAppointmentHandler
{
    public function __construct(
        private AppointmentRequestServiceInterface $appointmentRequestService,
    ) {
    }

    public function __invoke(RequestAppointmentInputDTO $dto): AppointmentOutputDTO
    {
        return $this->appointmentRequestService->requestAppointment(
            slotStartTime: $dto->slotStartTime,
            modality: $dto->modality,
            fullName: $dto->fullName,
            phone: $dto->phone,
            email: $dto->email,
            city: $dto->city,
            country: $dto->country,
            lockToken: $dto->lockToken,
        );
    }
}
