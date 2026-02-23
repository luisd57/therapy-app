<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\GetAppointmentInputDTO;
use App\Application\Appointment\DTO\Output\AppointmentOutputDTO;
use App\Domain\Appointment\Exception\AppointmentNotFoundException;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\ValueObject\AppointmentId;

final readonly class GetAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
    ) {
    }

    public function __invoke(GetAppointmentInputDTO $dto): AppointmentOutputDTO
    {
        $appointment = $this->appointmentRepository->findById(
            AppointmentId::fromString($dto->appointmentId)
        );

        if ($appointment === null) {
            throw new AppointmentNotFoundException($dto->appointmentId);
        }

        return AppointmentOutputDTO::fromEntity($appointment);
    }
}
