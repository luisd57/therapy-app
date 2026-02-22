<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\ListAppointmentsInputDTO;
use App\Application\Appointment\DTO\Output\AppointmentOutputDTO;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\ValueObject\AppointmentStatus;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ListAppointmentsHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
    ) {
    }

    /**
     * @return ArrayCollection<int, AppointmentOutputDTO>
     */
    public function __invoke(ListAppointmentsInputDTO $dto): ArrayCollection
    {
        if ($dto->status !== null) {
            $status = AppointmentStatus::from($dto->status);
            $appointments = $this->appointmentRepository->findByStatus($status);
        } else {
            $appointments = $this->appointmentRepository->findAll();
        }

        return new ArrayCollection(
            $appointments->map(
                fn ($appointment) => AppointmentOutputDTO::fromEntity($appointment)
            )->toArray()
        );
    }
}
