<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\AddScheduleExceptionInputDTO;
use App\Application\Appointment\Handler\AddScheduleExceptionHandler;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\Service\PracticeTimezoneProviderInterface;
use Symfony\Component\Clock\ClockInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Id\UserId;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use DateTimeZone;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AddScheduleExceptionHandlerTest extends TestCase
{
    private ScheduleExceptionRepositoryInterface&MockObject $exceptionRepository;
    private ClockInterface&MockObject $clock;
    private User $therapist;
    private AddScheduleExceptionHandler $handler;

    protected function setUp(): void
    {
        $this->exceptionRepository = $this->createMock(ScheduleExceptionRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-05-01T00:00:00+00:00'));

        $practiceTimezoneProvider = $this->createMock(PracticeTimezoneProviderInterface::class);
        $practiceTimezoneProvider->method('getTimeZone')->willReturn(new DateTimeZone('America/Caracas'));

        $this->therapist = DomainTestHelper::createTherapist();
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('getByIdOrFail')->willReturn($this->therapist);

        $this->handler = new AddScheduleExceptionHandler(
            $this->exceptionRepository,
            $userRepository,
            $this->clock,
            $practiceTimezoneProvider,
        );
    }

    public function testHandleSuccessCreatesExceptionAndSaves(): void
    {
        $therapistId = $this->therapist->getId()->getValue();

        $this->exceptionRepository
            ->expects($this->once())
            ->method('save');

        $input = new AddScheduleExceptionInputDTO(
            therapistId: $therapistId,
            startDateTime: '2026-06-01T09:00:00-04:00',
            endDateTime: '2026-06-01T17:00:00-04:00',
            reason: 'Personal day off',
            isAllDay: false,
        );

        $result = $this->handler->__invoke($input);

        $this->assertSame('Personal day off', $result->reason);
        $this->assertFalse($result->isAllDay);
        $this->assertNotEmpty($result->id);
        $this->assertNotEmpty($result->startDateTime);
        $this->assertNotEmpty($result->endDateTime);
        $this->assertNotEmpty($result->createdAt);
    }

    public function testAllDayExceptionIsSnappedToThePracticeDayBeforeItIsSaved(): void
    {
        $saved = null;
        $this->exceptionRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (ScheduleException $scheduleException) use (&$saved): void {
                $saved = $scheduleException;
            });

        // A caller on UTC+14 marking their own working day off. Read in Caracas
        // that range is 1 June 10:00 to 18:00.
        $this->handler->__invoke(new AddScheduleExceptionInputDTO(
            therapistId: UserId::generate()->getValue(),
            startDateTime: '2026-06-02T04:00:00+14:00',
            endDateTime: '2026-06-02T12:00:00+14:00',
            reason: 'Away',
            isAllDay: true,
        ));

        $this->assertInstanceOf(ScheduleException::class, $saved);
        $this->assertSame(
            '2026-06-01T04:00:00+00:00',
            $saved->getStartDateTime()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP'),
        );
        $this->assertSame(
            '2026-06-02T04:00:00+00:00',
            $saved->getEndDateTime()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP'),
        );
    }
}
