<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\ListAppointmentsInputDTO;
use App\Application\Appointment\Handler\ListAppointmentsHandler;
use App\Application\Shared\DTO\PaginationInputDTO;
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
            ->method('findAllPaginated')
            ->with(0, 20)
            ->willReturn(new ArrayCollection([$appointment]));

        $this->appointmentRepository
            ->expects($this->once())
            ->method('countAll')
            ->willReturn(1);

        $result = $this->handler->__invoke(new ListAppointmentsInputDTO());

        $this->assertCount(1, $result->items);
        $this->assertSame($appointment->getId()->getValue(), $result->items->first()->id);
        $this->assertSame(1, $result->total);
        $this->assertSame(1, $result->page);
        $this->assertSame(20, $result->limit);
    }

    public function testListAppointmentsByStatus(): void
    {
        $appointment = DomainTestHelper::createRequestedAppointment();

        $this->appointmentRepository
            ->expects($this->once())
            ->method('findByStatusPaginated')
            ->with(AppointmentStatus::REQUESTED, 0, 20)
            ->willReturn(new ArrayCollection([$appointment]));

        $this->appointmentRepository
            ->expects($this->once())
            ->method('countByStatus')
            ->with(AppointmentStatus::REQUESTED)
            ->willReturn(1);

        $result = $this->handler->__invoke(new ListAppointmentsInputDTO(status: 'REQUESTED'));

        $this->assertCount(1, $result->items);
        $this->assertSame('REQUESTED', $result->items->first()->status);
        $this->assertSame(1, $result->total);
    }

    public function testListReturnsEmptyCollection(): void
    {
        $this->appointmentRepository
            ->expects($this->once())
            ->method('findAllPaginated')
            ->with(0, 20)
            ->willReturn(new ArrayCollection());

        $this->appointmentRepository
            ->expects($this->once())
            ->method('countAll')
            ->willReturn(0);

        $result = $this->handler->__invoke(new ListAppointmentsInputDTO());

        $this->assertCount(0, $result->items);
        $this->assertSame(0, $result->total);
    }

    public function testListWithCustomPagination(): void
    {
        $appointment = DomainTestHelper::createRequestedAppointment();

        $this->appointmentRepository
            ->expects($this->once())
            ->method('findAllPaginated')
            ->with(10, 5)
            ->willReturn(new ArrayCollection([$appointment]));

        $this->appointmentRepository
            ->expects($this->once())
            ->method('countAll')
            ->willReturn(15);

        $result = $this->handler->__invoke(new ListAppointmentsInputDTO(
            pagination: new PaginationInputDTO(page: 3, limit: 5),
        ));

        $this->assertCount(1, $result->items);
        $this->assertSame(3, $result->page);
        $this->assertSame(5, $result->limit);
        $this->assertSame(15, $result->total);
        $this->assertSame(3, $result->totalPages);
    }
}
