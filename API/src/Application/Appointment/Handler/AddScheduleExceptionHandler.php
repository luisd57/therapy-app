<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\AddScheduleExceptionInputDTO;
use App\Application\Appointment\DTO\Output\ScheduleExceptionOutputDTO;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\Id\ExceptionId;
use App\Domain\User\Id\UserId;
use Symfony\Component\Clock\ClockInterface;
use DateTimeImmutable;

final readonly class AddScheduleExceptionHandler
{
    public function __construct(
        private ScheduleExceptionRepositoryInterface $exceptionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AddScheduleExceptionInputDTO $dto): ScheduleExceptionOutputDTO
    {
        $therapistId = UserId::fromString($dto->therapistId);
        $startDateTime = new DateTimeImmutable($dto->startDateTime);
        $endDateTime = new DateTimeImmutable($dto->endDateTime);

        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: $therapistId,
            startDateTime: $startDateTime,
            endDateTime: $endDateTime,
            now: $this->clock->now(),
            reason: $dto->reason,
            isAllDay: $dto->isAllDay,
        );

        $this->exceptionRepository->save($exception);

        return ScheduleExceptionOutputDTO::fromEntity($exception);
    }
}
