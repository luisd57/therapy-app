<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\ListScheduleExceptionsInputDTO;
use App\Application\Appointment\DTO\Output\ScheduleExceptionOutputDTO;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ListScheduleExceptionsHandler
{
    public function __construct(
        private ScheduleExceptionRepositoryInterface $exceptionRepository,
    ) {
    }

    /**
     * @return ArrayCollection<int, ScheduleExceptionOutputDTO>
     */
    public function __invoke(ListScheduleExceptionsInputDTO $dto): ArrayCollection
    {
        $exceptions = $this->exceptionRepository->findByTherapistAndDateRange(
            UserId::fromString($dto->therapistId),
            new DateTimeImmutable($dto->from),
            new DateTimeImmutable($dto->to),
        );

        return new ArrayCollection(
            $exceptions->map(
                fn (ScheduleException $scheduleException) => ScheduleExceptionOutputDTO::fromEntity($scheduleException),
            )->toArray(),
        );
    }
}
