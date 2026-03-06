<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\UpdatePaymentStatusInputDTO;
use App\Application\Appointment\Handler\UpdatePaymentStatusHandler;
use App\Domain\Appointment\Exception\AppointmentNotFoundException;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use Symfony\Component\Clock\ClockInterface;
use App\Domain\Appointment\Id\AppointmentId;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdatePaymentStatusHandlerTest extends TestCase
{
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private ClockInterface&MockObject $clock;
    private UpdatePaymentStatusHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable());
        $this->handler = new UpdatePaymentStatusHandler($this->appointmentRepository, $this->clock);
    }

    public function testMarkPaymentVerified(): void
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

        $result = $this->handler->__invoke(new UpdatePaymentStatusInputDTO(
            appointmentId: $id->getValue(),
            paymentVerified: true,
        ));

        $this->assertTrue($result->paymentVerified);
    }

    public function testMarkPaymentUnverified(): void
    {
        $id = AppointmentId::generate();
        $appointment = DomainTestHelper::createConfirmedAppointment(id: $id);
        $appointment->markPaymentVerified(new \DateTimeImmutable());

        $this->appointmentRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepository
            ->expects($this->once())
            ->method('save');

        $result = $this->handler->__invoke(new UpdatePaymentStatusInputDTO(
            appointmentId: $id->getValue(),
            paymentVerified: false,
        ));

        $this->assertFalse($result->paymentVerified);
    }

    public function testUpdatePaymentForNonExistentAppointmentThrowsException(): void
    {
        $this->appointmentRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(AppointmentNotFoundException::class);

        $this->handler->__invoke(new UpdatePaymentStatusInputDTO(
            appointmentId: AppointmentId::generate()->getValue(),
            paymentVerified: true,
        ));
    }
}
