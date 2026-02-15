<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\AddScheduleExceptionInputDTO;
use App\Application\Appointment\DTO\Output\ScheduleExceptionDTO;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\ValueObject\ExceptionId;
use App\Domain\User\ValueObject\UserId;
use DateTimeImmutable;

final readonly class AddScheduleExceptionHandler
{
    public function __construct(
        private ScheduleExceptionRepositoryInterface $exceptionRepository,
    ) {
    }

    public function handle(AddScheduleExceptionInputDTO $input): ScheduleExceptionDTO
    {
        $therapistId = UserId::fromString($input->therapistId);
        $startDateTime = new DateTimeImmutable($input->startDateTime);
        $endDateTime = new DateTimeImmutable($input->endDateTime);

        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: $therapistId,
            startDateTime: $startDateTime,
            endDateTime: $endDateTime,
            reason: $input->reason,
            isAllDay: $input->isAllDay,
        );

        $this->exceptionRepository->save($exception);

        return ScheduleExceptionDTO::fromEntity($exception);
    }
}
