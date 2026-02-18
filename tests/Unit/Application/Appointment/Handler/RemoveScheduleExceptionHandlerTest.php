<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\RemoveScheduleExceptionInputDTO;
use App\Application\Appointment\Handler\RemoveScheduleExceptionHandler;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Exception\ScheduleConflictException;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\ValueObject\ExceptionId;
use App\Domain\User\ValueObject\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RemoveScheduleExceptionHandlerTest extends TestCase
{
    private ScheduleExceptionRepositoryInterface&MockObject $exceptionRepository;
    private RemoveScheduleExceptionHandler $handler;

    protected function setUp(): void
    {
        $this->exceptionRepository = $this->createMock(ScheduleExceptionRepositoryInterface::class);

        $this->handler = new RemoveScheduleExceptionHandler(
            $this->exceptionRepository,
        );
    }

    public function testHandleSuccessFindsAndDeletes(): void
    {
        $exceptionId = ExceptionId::generate();
        $therapistId = UserId::generate();

        $exception = ScheduleException::reconstitute(
            id: $exceptionId,
            therapistId: $therapistId,
            startDateTime: new \DateTimeImmutable('2025-06-01 09:00:00'),
            endDateTime: new \DateTimeImmutable('2025-06-01 17:00:00'),
            reason: 'Day off',
            isAllDay: true,
            createdAt: new \DateTimeImmutable(),
        );

        $this->exceptionRepository
            ->method('findById')
            ->willReturn($exception);

        $this->exceptionRepository
            ->expects($this->once())
            ->method('delete')
            ->with($exception);

        $input = new RemoveScheduleExceptionInputDTO(
            exceptionId: $exceptionId->getValue(),
            therapistId: $therapistId->getValue(),
        );

        ($this->handler)($input);
    }

    public function testHandleNotFoundThrowsScheduleConflictException(): void
    {
        $exceptionId = ExceptionId::generate();

        $this->exceptionRepository
            ->method('findById')
            ->willReturn(null);

        $this->exceptionRepository
            ->expects($this->never())
            ->method('delete');

        $input = new RemoveScheduleExceptionInputDTO(
            exceptionId: $exceptionId->getValue(),
            therapistId: UserId::generate()->getValue(),
        );

        $this->expectException(ScheduleConflictException::class);
        ($this->handler)($input);
    }
}
