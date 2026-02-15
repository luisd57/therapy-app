<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\DeleteTherapistScheduleInputDTO;
use App\Application\Appointment\Handler\DeleteTherapistScheduleHandler;
use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Exception\ScheduleConflictException;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\ValueObject\ScheduleId;
use App\Domain\Appointment\ValueObject\WeekDay;
use App\Domain\User\ValueObject\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeleteTherapistScheduleHandlerTest extends TestCase
{
    private TherapistScheduleRepositoryInterface&MockObject $scheduleRepository;
    private DeleteTherapistScheduleHandler $handler;

    protected function setUp(): void
    {
        $this->scheduleRepository = $this->createMock(TherapistScheduleRepositoryInterface::class);

        $this->handler = new DeleteTherapistScheduleHandler(
            $this->scheduleRepository,
        );
    }

    public function testHandleSuccessFindsAndDeletes(): void
    {
        $scheduleId = ScheduleId::generate();
        $now = new \DateTimeImmutable();

        $schedule = TherapistSchedule::reconstitute(
            id: $scheduleId,
            therapistId: UserId::generate(),
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '12:00',
            supportsOnline: true,
            supportsInPerson: true,
            isActive: true,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->scheduleRepository
            ->method('findById')
            ->willReturn($schedule);

        $this->scheduleRepository
            ->expects($this->once())
            ->method('delete')
            ->with($schedule);

        $input = new DeleteTherapistScheduleInputDTO(
            scheduleId: $scheduleId->getValue(),
            therapistId: UserId::generate()->getValue(),
        );

        $this->handler->handle($input);
    }

    public function testHandleNotFoundThrowsScheduleConflictException(): void
    {
        $scheduleId = ScheduleId::generate();

        $this->scheduleRepository
            ->method('findById')
            ->willReturn(null);

        $this->scheduleRepository
            ->expects($this->never())
            ->method('delete');

        $input = new DeleteTherapistScheduleInputDTO(
            scheduleId: $scheduleId->getValue(),
            therapistId: UserId::generate()->getValue(),
        );

        $this->expectException(ScheduleConflictException::class);
        $this->handler->handle($input);
    }
}
