<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\ListScheduleExceptionsInputDTO;
use App\Application\Appointment\DTO\Output\ScheduleExceptionDTO;
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
     * @return ArrayCollection<int, ScheduleExceptionDTO>
     */
    public function handle(ListScheduleExceptionsInputDTO $input): ArrayCollection
    {
        $exceptions = $this->exceptionRepository->findByTherapistAndDateRange(
            UserId::fromString($input->therapistId),
            new DateTimeImmutable($input->from),
            new DateTimeImmutable($input->to),
        );

        return new ArrayCollection(
            $exceptions->map(
                fn (ScheduleException $e) => ScheduleExceptionDTO::fromEntity($e),
            )->toArray(),
        );
    }
}
