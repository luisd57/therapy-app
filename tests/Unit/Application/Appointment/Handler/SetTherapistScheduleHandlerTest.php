<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\SetTherapistScheduleInputDTO;
use App\Application\Appointment\Handler\SetTherapistScheduleHandler;
use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Exception\ScheduleConflictException;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\ValueObject\ScheduleId;
use App\Domain\Appointment\ValueObject\WeekDay;
use App\Domain\User\ValueObject\UserId;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SetTherapistScheduleHandlerTest extends TestCase
{
    private TherapistScheduleRepositoryInterface&MockObject $scheduleRepository;
    private SetTherapistScheduleHandler $handler;

    protected function setUp(): void
    {
        $this->scheduleRepository = $this->createMock(TherapistScheduleRepositoryInterface::class);

        $this->handler = new SetTherapistScheduleHandler(
            $this->scheduleRepository,
        );
    }

    public function testHandleSuccessCreatesScheduleAndReturnsDTO(): void
    {
        $therapistId = UserId::generate()->getValue();

        $this->scheduleRepository
            ->method('findActiveByTherapistAndDay')
            ->willReturn(new ArrayCollection());

        $this->scheduleRepository
            ->expects($this->once())
            ->method('save');

        $input = new SetTherapistScheduleInputDTO(
            therapistId: $therapistId,
            dayOfWeek: 1,
            startTime: '09:00',
            endTime: '12:00',
            supportsOnline: true,
            supportsInPerson: false,
        );

        $result = $this->handler->__invoke($input);

        $this->assertSame(1, $result->dayOfWeek);
        $this->assertSame('Monday', $result->dayName);
        $this->assertSame('09:00', $result->startTime);
        $this->assertSame('12:00', $result->endTime);
        $this->assertTrue($result->supportsOnline);
        $this->assertFalse($result->supportsInPerson);
        $this->assertTrue($result->isActive);
    }

    public function testHandleOverlapThrowsScheduleConflictException(): void
    {
        $therapistId = UserId::generate();
        $now = new \DateTimeImmutable();

        $existingSchedule = TherapistSchedule::reconstitute(
            id: ScheduleId::generate(),
            therapistId: $therapistId,
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
            ->method('findActiveByTherapistAndDay')
            ->willReturn(new ArrayCollection([$existingSchedule]));

        $this->scheduleRepository
            ->expects($this->never())
            ->method('save');

        $input = new SetTherapistScheduleInputDTO(
            therapistId: $therapistId->getValue(),
            dayOfWeek: 1,
            startTime: '10:00',
            endTime: '13:00',
        );

        $this->expectException(ScheduleConflictException::class);
        $this->handler->__invoke($input);
    }
}
