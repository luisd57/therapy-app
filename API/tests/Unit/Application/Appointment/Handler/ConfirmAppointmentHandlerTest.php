<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\ConfirmAppointmentInputDTO;
use App\Application\Appointment\Handler\ConfirmAppointmentHandler;
use App\Domain\Appointment\Exception\AppointmentNotFoundException;
use App\Domain\Appointment\Exception\InvalidStatusTransitionException;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Service\AppointmentEmailSenderInterface;
use Symfony\Component\Clock\ClockInterface;
use App\Domain\Appointment\Id\AppointmentId;
use App\Domain\Appointment\Enum\AppointmentStatus;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ConfirmAppointmentHandlerTest extends TestCase
{
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private AppointmentEmailSenderInterface&MockObject $emailSender;
    private ClockInterface&MockObject $clock;
    private ConfirmAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->emailSender = $this->createMock(AppointmentEmailSenderInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable());
        $this->handler = new ConfirmAppointmentHandler($this->appointmentRepository, $this->emailSender, $this->clock);
    }

    public function testConfirmRequestedAppointment(): void
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
            ->method('sendConfirmationToPatient');

        $result = $this->handler->__invoke(new ConfirmAppointmentInputDTO(
            appointmentId: $id->getValue(),
        ));

        $this->assertSame('CONFIRMED', $result->status);
    }

    public function testConfirmNonExistentAppointmentThrowsException(): void
    {
        $this->appointmentRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(AppointmentNotFoundException::class);

        $this->handler->__invoke(new ConfirmAppointmentInputDTO(
            appointmentId: AppointmentId::generate()->getValue(),
        ));
    }

    public function testConfirmAlreadyConfirmedAppointmentThrowsException(): void
    {
        $id = AppointmentId::generate();
        $appointment = DomainTestHelper::createConfirmedAppointment(id: $id);

        $this->appointmentRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($appointment);

        $this->expectException(InvalidStatusTransitionException::class);

        $this->handler->__invoke(new ConfirmAppointmentInputDTO(
            appointmentId: $id->getValue(),
        ));
    }
}
