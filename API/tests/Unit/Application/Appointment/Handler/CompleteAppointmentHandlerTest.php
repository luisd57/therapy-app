<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\CompleteAppointmentInputDTO;
use App\Application\Appointment\Handler\CompleteAppointmentHandler;
use App\Domain\Appointment\Exception\AppointmentNotFoundException;
use App\Domain\Appointment\Exception\InvalidStatusTransitionException;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Id\AppointmentId;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CompleteAppointmentHandlerTest extends TestCase
{
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private CompleteAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->handler = new CompleteAppointmentHandler($this->appointmentRepository);
    }

    public function testCompleteConfirmedAppointment(): void
    {
        $id = AppointmentId::generate();
        $appointment = DomainTestHelper::createConfirmedAppointment(id: $id);

        $this->appointmentRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepository
            ->expects($this->once())
            ->method('save');

        $result = $this->handler->__invoke(new CompleteAppointmentInputDTO(
            appointmentId: $id->getValue(),
        ));

        $this->assertSame('COMPLETED', $result->status);
    }

    public function testCompleteRequestedAppointmentThrowsException(): void
    {
        $id = AppointmentId::generate();
        $appointment = DomainTestHelper::createRequestedAppointment(id: $id);

        $this->appointmentRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($appointment);

        $this->expectException(InvalidStatusTransitionException::class);

        $this->handler->__invoke(new CompleteAppointmentInputDTO(
            appointmentId: $id->getValue(),
        ));
    }

    public function testCompleteNonExistentAppointmentThrowsException(): void
    {
        $this->appointmentRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(AppointmentNotFoundException::class);

        $this->handler->__invoke(new CompleteAppointmentInputDTO(
            appointmentId: AppointmentId::generate()->getValue(),
        ));
    }
}
