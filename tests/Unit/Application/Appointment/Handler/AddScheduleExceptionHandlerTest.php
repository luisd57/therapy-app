<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\AddScheduleExceptionInputDTO;
use App\Application\Appointment\Handler\AddScheduleExceptionHandler;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AddScheduleExceptionHandlerTest extends TestCase
{
    private ScheduleExceptionRepositoryInterface&MockObject $exceptionRepository;
    private AddScheduleExceptionHandler $handler;

    protected function setUp(): void
    {
        $this->exceptionRepository = $this->createMock(ScheduleExceptionRepositoryInterface::class);

        $this->handler = new AddScheduleExceptionHandler(
            $this->exceptionRepository,
        );
    }

    public function testHandleSuccessCreatesExceptionAndSaves(): void
    {
        $therapistId = UserId::generate()->getValue();

        $this->exceptionRepository
            ->expects($this->once())
            ->method('save');

        $input = new AddScheduleExceptionInputDTO(
            therapistId: $therapistId,
            startDateTime: '2025-06-01 09:00:00',
            endDateTime: '2025-06-01 17:00:00',
            reason: 'Personal day off',
            isAllDay: true,
        );

        $result = ($this->handler)($input);

        $this->assertSame('Personal day off', $result->reason);
        $this->assertTrue($result->isAllDay);
        $this->assertNotEmpty($result->id);
        $this->assertNotEmpty($result->startDateTime);
        $this->assertNotEmpty($result->endDateTime);
        $this->assertNotEmpty($result->createdAt);
    }
}
