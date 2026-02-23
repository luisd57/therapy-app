<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\ListAppointmentsInputDTO;
use App\Application\Appointment\Handler\ListAppointmentsHandler;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\ValueObject\AppointmentStatus;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ListAppointmentsHandlerTest extends TestCase
{
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private ListAppointmentsHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->handler = new ListAppointmentsHandler($this->appointmentRepository);
    }

    public function testListAllAppointments(): void
    {
        $appointment = DomainTestHelper::createRequestedAppointment();

        $this->appointmentRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn(new ArrayCollection([$appointment]));

        $result = $this->handler->__invoke(new ListAppointmentsInputDTO());

        $this->assertCount(1, $result);
        $this->assertSame($appointment->getId()->getValue(), $result->first()->id);
    }

    public function testListAppointmentsByStatus(): void
    {
        $appointment = DomainTestHelper::createRequestedAppointment();

        $this->appointmentRepository
            ->expects($this->once())
            ->method('findByStatus')
            ->with(AppointmentStatus::REQUESTED)
            ->willReturn(new ArrayCollection([$appointment]));

        $result = $this->handler->__invoke(new ListAppointmentsInputDTO(status: 'REQUESTED'));

        $this->assertCount(1, $result);
        $this->assertSame('REQUESTED', $result->first()->status);
    }

    public function testListReturnsEmptyCollection(): void
    {
        $this->appointmentRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn(new ArrayCollection());

        $result = $this->handler->__invoke(new ListAppointmentsInputDTO());

        $this->assertCount(0, $result);
    }
}
