<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\CancelAppointmentInputDTO;
use App\Application\Appointment\Handler\CancelAppointmentHandler;
use App\Domain\Appointment\Exception\AppointmentNotFoundException;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Service\AppointmentEmailSenderInterface;
use Symfony\Component\Clock\ClockInterface;
use App\Domain\Appointment\Id\AppointmentId;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CancelAppointmentHandlerTest extends TestCase
{
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private AppointmentEmailSenderInterface&MockObject $emailSender;
    private ClockInterface&MockObject $clock;
    private CancelAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->emailSender = $this->createMock(AppointmentEmailSenderInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable());
        $this->handler = new CancelAppointmentHandler($this->appointmentRepository, $this->emailSender, $this->clock);
    }

    public function testCancelRequestedAppointment(): void
    {
        $id = AppointmentId::generate();
        $appointment = DomainTestHelper::createRequestedAppointment(id: $id);

        $this->appointmentRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepository
            ->expects($this->once())
            ->method('save');

        $this->emailSender
            ->expects($this->once())
            ->method('sendCancellationToPatient');

        $result = $this->handler->__invoke(new CancelAppointmentInputDTO(
            appointmentId: $id->getValue(),
        ));

        $this->assertSame('CANCELLED', $result->status);
    }

    public function testCancelConfirmedAppointment(): void
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

        $this->emailSender
            ->expects($this->once())
            ->method('sendCancellationToPatient');

        $result = $this->handler->__invoke(new CancelAppointmentInputDTO(
            appointmentId: $id->getValue(),
        ));

        $this->assertSame('CANCELLED', $result->status);
    }

    public function testCancelNonExistentAppointmentThrowsException(): void
    {
        $this->appointmentRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(AppointmentNotFoundException::class);

        $this->handler->__invoke(new CancelAppointmentInputDTO(
            appointmentId: AppointmentId::generate()->getValue(),
        ));
    }
}
