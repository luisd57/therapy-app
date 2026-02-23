<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\BookAppointmentInputDTO;
use App\Application\Appointment\Handler\BookAppointmentHandler;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class BookAppointmentHandlerTest extends TestCase
{
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private BookAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->handler = new BookAppointmentHandler($this->appointmentRepository, 50);
    }

    public function testBookCreatesConfirmedAppointment(): void
    {
        $this->appointmentRepository
            ->expects($this->once())
            ->method('save');

        $result = $this->handler->__invoke(new BookAppointmentInputDTO(
            slotStartTime: '2026-04-01T10:00:00',
            modality: 'ONLINE',
            fullName: 'John Doe',
            phone: '+1234567890',
            email: 'john@example.com',
            city: 'New York',
            country: 'USA',
        ));

        $this->assertSame('CONFIRMED', $result->status);
        $this->assertSame('John Doe', $result->fullName);
        $this->assertSame('ONLINE', $result->modality);
        $this->assertNull($result->patientId);
    }

    public function testBookWithPatientId(): void
    {
        $this->appointmentRepository
            ->expects($this->once())
            ->method('save');

        $result = $this->handler->__invoke(new BookAppointmentInputDTO(
            slotStartTime: '2026-04-01T10:00:00',
            modality: 'IN_PERSON',
            fullName: 'Jane Smith',
            phone: '+9876543210',
            email: 'jane@example.com',
            city: 'Los Angeles',
            country: 'USA',
            patientId: '019525f3-5be1-7190-a6e1-aaa000000001',
        ));

        $this->assertSame('CONFIRMED', $result->status);
        $this->assertSame('019525f3-5be1-7190-a6e1-aaa000000001', $result->patientId);
    }
}
