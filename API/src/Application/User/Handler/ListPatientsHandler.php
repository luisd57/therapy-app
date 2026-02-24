<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\Shared\DTO\PaginatedResultDTO;
use App\Application\User\DTO\Input\ListPatientsInputDTO;
use App\Application\User\DTO\Output\UserOutputDTO;
use App\Domain\User\Repository\UserRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ListPatientsHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(ListPatientsInputDTO $dto): PaginatedResultDTO
    {
        $pagination = $dto->pagination;

        $patients = $this->userRepository->findActivePatientsPaginated(
            $pagination->offset,
            $pagination->limit,
        );
        $total = $this->userRepository->countActivePatients();

        $outputDtos = new ArrayCollection(
            $patients->map(
                fn ($user) => UserOutputDTO::fromEntity($user)
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
