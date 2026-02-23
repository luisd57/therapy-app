<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\UpdatePaymentStatusInputDTO;
use App\Application\Appointment\DTO\Output\AppointmentOutputDTO;
use App\Domain\Appointment\Exception\AppointmentNotFoundException;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\ValueObject\AppointmentId;

final readonly class UpdatePaymentStatusHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
    ) {
    }

    public function __invoke(UpdatePaymentStatusInputDTO $dto): AppointmentOutputDTO
    {
        $appointment = $this->appointmentRepository->findById(
            AppointmentId::fromString($dto->appointmentId)
        );

        if ($appointment === null) {
            throw new AppointmentNotFoundException($dto->appointmentId);
        }

        if ($dto->paymentVerified) {
            $appointment->markPaymentVerified();
        } else {
            $appointment->markPaymentUnverified();
        }

        $this->appointmentRepository->save($appointment);

        return AppointmentOutputDTO::fromEntity($appointment);
    }
}
