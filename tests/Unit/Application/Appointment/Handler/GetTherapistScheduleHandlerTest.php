<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\Handler\GetTherapistScheduleHandler;
use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\ValueObject\ScheduleId;
use App\Domain\Appointment\ValueObject\WeekDay;
use App\Domain\User\ValueObject\UserId;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetTherapistScheduleHandlerTest extends TestCase
{
    private TherapistScheduleRepositoryInterface&MockObject $scheduleRepository;
    private GetTherapistScheduleHandler $handler;

    protected function setUp(): void
    {
        $this->scheduleRepository = $this->createMock(TherapistScheduleRepositoryInterface::class);

        $this->handler = new GetTherapistScheduleHandler(
            $this->scheduleRepository,
        );
    }

    public function testHandleReturnsCollectionOfDTOs(): void
    {
        $therapistId = UserId::generate();
        $now = new \DateTimeImmutable();

        $schedule1 = TherapistSchedule::reconstitute(
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

        $schedule2 = TherapistSchedule::reconstitute(
            id: ScheduleId::generate(),
            therapistId: $therapistId,
            dayOfWeek: WeekDay::WEDNESDAY,
            startTime: '14:00',
            endTime: '18:00',
            supportsOnline: false,
            supportsInPerson: true,
            isActive: true,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->scheduleRepository
            ->method('findActiveByTherapist')
            ->willReturn(new ArrayCollection([$schedule1, $schedule2]));

        $result = ($this->handler)($therapistId->getValue());

        $this->assertCount(2, $result);

        $first = $result->first();
        $this->assertSame(1, $first->dayOfWeek);
        $this->assertSame('Monday', $first->dayName);
        $this->assertSame('09:00', $first->startTime);
        $this->assertSame('12:00', $first->endTime);

        $last = $result->last();
        $this->assertSame(3, $last->dayOfWeek);
        $this->assertSame('Wednesday', $last->dayName);
        $this->assertSame('14:00', $last->startTime);
        $this->assertSame('18:00', $last->endTime);
    }
}
