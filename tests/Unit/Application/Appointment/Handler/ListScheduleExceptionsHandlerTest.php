<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\ListScheduleExceptionsInputDTO;
use App\Application\Appointment\Handler\ListScheduleExceptionsHandler;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\ValueObject\ExceptionId;
use App\Domain\User\ValueObject\UserId;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ListScheduleExceptionsHandlerTest extends TestCase
{
    private ScheduleExceptionRepositoryInterface&MockObject $exceptionRepository;
    private ListScheduleExceptionsHandler $handler;

    protected function setUp(): void
    {
        $this->exceptionRepository = $this->createMock(ScheduleExceptionRepositoryInterface::class);

        $this->handler = new ListScheduleExceptionsHandler(
            $this->exceptionRepository,
        );
    }

    public function testHandleReturnsCollectionOfDTOs(): void
    {
        $therapistId = UserId::generate();

        $exception1 = ScheduleException::reconstitute(
            id: ExceptionId::generate(),
            therapistId: $therapistId,
            startDateTime: new \DateTimeImmutable('2025-06-01 09:00:00'),
            endDateTime: new \DateTimeImmutable('2025-06-01 17:00:00'),
            reason: 'Day off',
            isAllDay: true,
            createdAt: new \DateTimeImmutable(),
        );

        $exception2 = ScheduleException::reconstitute(
            id: ExceptionId::generate(),
            therapistId: $therapistId,
            startDateTime: new \DateTimeImmutable('2025-06-15 10:00:00'),
            endDateTime: new \DateTimeImmutable('2025-06-15 12:00:00'),
            reason: 'Doctor appointment',
            isAllDay: false,
            createdAt: new \DateTimeImmutable(),
        );

        $this->exceptionRepository
            ->method('findByTherapistAndDateRange')
            ->willReturn(new ArrayCollection([$exception1, $exception2]));

        $input = new ListScheduleExceptionsInputDTO(
            therapistId: $therapistId->getValue(),
            from: '2025-06-01',
            to: '2025-06-30',
        );

        $result = $this->handler->handle($input);

        $this->assertCount(2, $result);

        $first = $result->first();
        $this->assertSame('Day off', $first->reason);
        $this->assertTrue($first->isAllDay);

        $last = $result->last();
        $this->assertSame('Doctor appointment', $last->reason);
        $this->assertFalse($last->isAllDay);
    }
}
