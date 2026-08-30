<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\UpdateTherapistScheduleInputDTO;
use App\Application\Appointment\Handler\UpdateTherapistScheduleHandler;
use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Exception\ScheduleConflictException;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use Symfony\Component\Clock\ClockInterface;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Enum\WeekDay;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateTherapistScheduleHandlerTest extends TestCase
{
    private TherapistScheduleRepositoryInterface&MockObject $scheduleRepository;
    private ClockInterface&MockObject $clock;
    private UpdateTherapistScheduleHandler $handler;

    protected function setUp(): void
    {
        $this->scheduleRepository = $this->createMock(TherapistScheduleRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable());

        $this->handler = new UpdateTherapistScheduleHandler(
            $this->scheduleRepository,
            $this->clock,
        );
    }

    public function testHandleSuccessUpdatesScheduleAndReturnsDTO(): void
    {
        $therapist = DomainTestHelper::createTherapist();
        $therapistId = $therapist->getId();
        $scheduleId = ScheduleId::generate();
        $now = new \DateTimeImmutable();

        $schedule = TherapistSchedule::reconstitute(
            id: $scheduleId,
            therapist: $therapist,
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
            ->method('findActiveByTherapistAndDay')
            ->willReturn(new ArrayCollection([$schedule]));

        $this->scheduleRepository
            ->expects($this->once())
            ->method('save');

        $input = new UpdateTherapistScheduleInputDTO(
            scheduleId: $scheduleId->getValue(),
            therapistId: $therapistId->getValue(),
            dayOfWeek: 2,
            startTime: '14:00',
            endTime: '17:00',
            supportsOnline: false,
            supportsInPerson: true,
        );

        $result = $this->handler->__invoke($input);

        $this->assertSame(2, $result->dayOfWeek);
        $this->assertSame('Tuesday', $result->dayName);
        $this->assertSame('14:00', $result->startTime);
        $this->assertSame('17:00', $result->endTime);
        $this->assertFalse($result->supportsOnline);
        $this->assertTrue($result->supportsInPerson);
    }

    public function testHandleNotFoundThrowsScheduleConflictException(): void
    {
        $scheduleId = ScheduleId::generate();
        $therapist = DomainTestHelper::createTherapist();
        $therapistId = $therapist->getId();

        $this->scheduleRepository
            ->method('findById')
            ->willReturn(null);

        $input = new UpdateTherapistScheduleInputDTO(
            scheduleId: $scheduleId->getValue(),
            therapistId: $therapistId->getValue(),
            dayOfWeek: 1,
            startTime: '09:00',
            endTime: '12:00',
        );

        $this->expectException(ScheduleConflictException::class);
        $this->handler->__invoke($input);
    }

    public function testHandleOverlapExcludingSelfThrowsScheduleConflictException(): void
    {
        $therapist = DomainTestHelper::createTherapist();
        $therapistId = $therapist->getId();
        $scheduleId = ScheduleId::generate();
        $otherScheduleId = ScheduleId::generate();
        $now = new \DateTimeImmutable();

        $schedule = TherapistSchedule::reconstitute(
            id: $scheduleId,
            therapist: $therapist,
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '12:00',
            supportsOnline: true,
            supportsInPerson: true,
            isActive: true,
            createdAt: $now,
            updatedAt: $now,
        );

        $otherSchedule = TherapistSchedule::reconstitute(
            id: $otherScheduleId,
            therapist: $therapist,
            dayOfWeek: WeekDay::MONDAY,
            startTime: '14:00',
            endTime: '17:00',
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
            ->method('findActiveByTherapistAndDay')
            ->willReturn(new ArrayCollection([$schedule, $otherSchedule]));

        $this->scheduleRepository
            ->expects($this->never())
            ->method('save');

        $input = new UpdateTherapistScheduleInputDTO(
            scheduleId: $scheduleId->getValue(),
            therapistId: $therapistId->getValue(),
            dayOfWeek: 1,
            startTime: '15:00',
            endTime: '18:00',
        );

        $this->expectException(ScheduleConflictException::class);
        $this->handler->__invoke($input);
    }
}
