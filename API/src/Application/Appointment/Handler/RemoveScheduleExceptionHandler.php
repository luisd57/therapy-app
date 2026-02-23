<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\RemoveScheduleExceptionInputDTO;
use App\Domain\Appointment\Exception\ScheduleConflictException;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\ValueObject\ExceptionId;

final readonly class RemoveScheduleExceptionHandler
{
    public function __construct(
        private ScheduleExceptionRepositoryInterface $exceptionRepository,
    ) {
    }

    public function __invoke(RemoveScheduleExceptionInputDTO $dto): void
    {
        $id = ExceptionId::fromString($dto->exceptionId);
        $exception = $this->exceptionRepository->findById($id);

        if ($exception === null) {
            throw ScheduleConflictException::exceptionNotFound($dto->exceptionId);
        }

        $this->exceptionRepository->delete($exception);
    }
}
