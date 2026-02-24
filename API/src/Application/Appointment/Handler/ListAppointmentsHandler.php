<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\ListAppointmentsInputDTO;
use App\Application\Appointment\DTO\Output\AppointmentOutputDTO;
use App\Application\Shared\DTO\PaginatedResultDTO;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\ValueObject\AppointmentStatus;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ListAppointmentsHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
    ) {
    }

    public function __invoke(ListAppointmentsInputDTO $dto): PaginatedResultDTO
    {
        $pagination = $dto->pagination;

        if ($dto->status !== null) {
            $status = AppointmentStatus::from($dto->status);
            $appointments = $this->appointmentRepository->findByStatusPaginated(
                $status,
                $pagination->offset,
                $pagination->limit,
            );
            $total = $this->appointmentRepository->countByStatus($status);
        } else {
            $appointments = $this->appointmentRepository->findAllPaginated(
                $pagination->offset,
                $pagination->limit,
            );
            $total = $this->appointmentRepository->countAll();
        }

        $outputDtos = new ArrayCollection(
            $appointments->map(
                fn ($appointment) => AppointmentOutputDTO::fromEntity($appointment)
            )->toArray()
        );

        return new PaginatedResultDTO(
            items: $outputDtos,
            total: $total,
            page: $pagination->page,
            limit: $pagination->limit,
        );
    }
}
