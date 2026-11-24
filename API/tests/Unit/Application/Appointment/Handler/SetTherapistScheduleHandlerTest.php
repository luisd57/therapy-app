<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\SetTherapistScheduleInputDTO;
use App\Application\Appointment\Handler\SetTherapistScheduleHandler;
use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Exception\ScheduleConflictException;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Enum\WeekDay;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\UsesUtcInstants;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\ClockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SetTherapistScheduleHandlerTest extends TestCase
{
    use UsesUtcInstants;

    private TherapistScheduleRepositoryInterface&MockObject $scheduleRepository;
    private UserRepositoryInterface&MockObject $userRepository;
    private ClockInterface&MockObject $clock;
    private User $therapist;
    private SetTherapistScheduleHandler $handler;

    protected function setUp(): void
    {
        $this->scheduleRepository = $this->createMock(TherapistScheduleRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(self::utc('2026-06-15 12:00:00'));

        $this->therapist = DomainTestHelper::createTherapist();
        $this->userRepository->method('getByIdOrFail')->willReturn($this->therapist);

        $this->handler = new SetTherapistScheduleHandler(
            $this->scheduleRepository,
            $this->userRepository,
            $this->clock,
        );
    }

    public function testHandleSuccessCreatesScheduleAndReturnsDTO(): void
    {
        $therapistId = $this->therapist->getId()->getValue();

        $this->scheduleRepository
            ->method('findActiveByTherapistAndDay')
            ->willReturn(new ArrayCollection());

        $saved = null;
        $this->scheduleRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (TherapistSchedule $schedule) use (&$saved): void {
                $saved = $schedule;
            });

        $input = new SetTherapistScheduleInputDTO(
            therapistId: $therapistId,
            dayOfWeek: 1,
            startTime: '09:00',
            endTime: '12:00',
            supportsOnline: true,
            supportsInPerson: false,
        );

        $result = $this->handler->__invoke($input);

        // The output DTO carries no created_at, so the Instant is read off the saved entity.
        $this->assertInstanceOf(TherapistSchedule::class, $saved);
        self::assertInstantIs('2026-06-15T12:00:00+00:00', $saved->getCreatedAt());

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
        $therapistId = $this->therapist->getId();
        $now = new \DateTimeImmutable();

        $existingSchedule = TherapistSchedule::reconstitute(
            id: ScheduleId::generate(),
            therapist: $this->therapist,
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

    /**
     * Each block touches an existing Monday block from 10:00 to 12:00.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function blocksTouchingTenToNoon(): iterable
    {
        yield 'starts when the existing block ends' => ['12:00', '14:00'];
        yield 'ends when the existing block starts' => ['08:00', '10:00'];
    }

    #[DataProvider('blocksTouchingTenToNoon')]
    public function testHandleBlockTouchingAnExistingBlockIsCreated(string $startTime, string $endTime): void
    {
        $existingSchedule = DomainTestHelper::createScheduleBlock($this->therapist, WeekDay::MONDAY, '10:00', '12:00');

        $this->scheduleRepository
            ->method('findActiveByTherapistAndDay')
            ->willReturn(new ArrayCollection([$existingSchedule]));

        $this->scheduleRepository
            ->expects($this->once())
            ->method('save');

        $input = new SetTherapistScheduleInputDTO(
            therapistId: $this->therapist->getId()->getValue(),
            dayOfWeek: 1,
            startTime: $startTime,
            endTime: $endTime,
        );

        $result = $this->handler->__invoke($input);

        $this->assertSame($startTime, $result->startTime);
        $this->assertSame($endTime, $result->endTime);
    }
}
