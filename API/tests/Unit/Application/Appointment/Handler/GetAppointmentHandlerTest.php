<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\GetAppointmentInputDTO;
use App\Application\Appointment\Handler\GetAppointmentHandler;
use App\Domain\Appointment\Exception\AppointmentNotFoundException;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Id\AppointmentId;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetAppointmentHandlerTest extends TestCase
{
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private GetAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->handler = new GetAppointmentHandler($this->appointmentRepository);
    }

    public function testGetExistingAppointment(): void
    {
        $id = AppointmentId::generate();
        $appointment = DomainTestHelper::createRequestedAppointment(id: $id);

        $this->appointmentRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($appointment);

        $result = $this->handler->__invoke(new GetAppointmentInputDTO(
            appointmentId: $id->getValue(),
        ));

        $this->assertSame($id->getValue(), $result->id);
    }

    public function testGetNonExistentAppointmentThrowsException(): void
    {
        $this->appointmentRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(AppointmentNotFoundException::class);

        $this->handler->__invoke(new GetAppointmentInputDTO(
            appointmentId: AppointmentId::generate()->getValue(),
        ));
    }
}
