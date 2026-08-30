<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\AddScheduleExceptionInputDTO;
use App\Application\Appointment\DTO\Output\ScheduleExceptionOutputDTO;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\Service\PracticeTimezoneProviderInterface;
use App\Domain\Appointment\Id\ExceptionId;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Id\UserId;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\Clock\ClockInterface;
use DateTimeImmutable;

final readonly class AddScheduleExceptionHandler
{
    public function __construct(
        private ScheduleExceptionRepositoryInterface $exceptionRepository,
        private UserRepositoryInterface $userRepository,
        private ClockInterface $clock,
        private PracticeTimezoneProviderInterface $practiceTimezoneProvider,
    ) {
    }

    public function __invoke(AddScheduleExceptionInputDTO $dto): ScheduleExceptionOutputDTO
    {
        $therapist = $this->userRepository->findById(UserId::fromString($dto->therapistId));

        if ($therapist === null) {
            throw new UserNotFoundException($dto->therapistId);
        }

        $startDateTime = new DateTimeImmutable($dto->startDateTime);
        $endDateTime = new DateTimeImmutable($dto->endDateTime);

        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapist: $therapist,
            startDateTime: $startDateTime,
            endDateTime: $endDateTime,
            now: $this->clock->now(),
            practiceTimeZone: $this->practiceTimezoneProvider->getTimeZone(),
            reason: $dto->reason,
            isAllDay: $dto->isAllDay,
        );

        $this->exceptionRepository->save($exception);

        return ScheduleExceptionOutputDTO::fromEntity($exception);
    }
}
