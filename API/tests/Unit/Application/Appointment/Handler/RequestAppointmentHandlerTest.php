<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\RequestAppointmentInputDTO;
use App\Application\Appointment\DTO\Output\AppointmentOutputDTO;
use App\Application\Appointment\Handler\RequestAppointmentHandler;
use App\Application\Appointment\Service\AppointmentRequestServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RequestAppointmentHandlerTest extends TestCase
{
    private AppointmentRequestServiceInterface&MockObject $appointmentRequestService;
    private RequestAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRequestService = $this->createMock(AppointmentRequestServiceInterface::class);

        $this->handler = new RequestAppointmentHandler(
            $this->appointmentRequestService,
        );
    }

    private function createInputDTO(?string $lockToken = null): RequestAppointmentInputDTO
    {
        return new RequestAppointmentInputDTO(
            slotStartTime: '2025-06-02 09:00:00',
            modality: 'ONLINE',
            fullName: 'Jane Doe',
            phone: '+1234567890',
            email: 'jane@example.com',
            city: 'Berlin',
            country: 'Germany',
            lockToken: $lockToken,
        );
    }

    private function createExpectedOutput(): AppointmentOutputDTO
    {
        return new AppointmentOutputDTO(
            id: '019525f3-5be1-7190-a6e1-aaa000000001',
            startTime: '2025-06-02T09:00:00+00:00',
            endTime: '2025-06-02T09:50:00+00:00',
            modality: 'ONLINE',
            status: 'REQUESTED',
            fullName: 'Jane Doe',
            email: 'jane@example.com',
            phone: '+1234567890',
            city: 'Berlin',
            country: 'Germany',
            patientId: null,
            paymentVerified: false,
            createdAt: '2025-06-02T09:00:00+00:00',
            updatedAt: '2025-06-02T09:00:00+00:00',
        );
    }

    public function testHandleDelegatesToServiceWithoutLockToken(): void
    {
        $input = $this->createInputDTO();
        $expectedOutput = $this->createExpectedOutput();

        $this->appointmentRequestService
            ->expects($this->once())
            ->method('requestAppointment')
            ->with(
                '2025-06-02 09:00:00',
                'ONLINE',
                'Jane Doe',
                '+1234567890',
                'jane@example.com',
                'Berlin',
                'Germany',
                null,
            )
            ->willReturn($expectedOutput);

        $result = $this->handler->__invoke($input);

        $this->assertSame($expectedOutput, $result);
    }

    public function testHandleDelegatesToServiceWithLockToken(): void
    {
        $input = $this->createInputDTO('valid-lock-token');
        $expectedOutput = $this->createExpectedOutput();

        $this->appointmentRequestService
            ->expects($this->once())
            ->method('requestAppointment')
            ->with(
                '2025-06-02 09:00:00',
                'ONLINE',
                'Jane Doe',
                '+1234567890',
                'jane@example.com',
                'Berlin',
                'Germany',
                'valid-lock-token',
            )
            ->willReturn($expectedOutput);

        $result = $this->handler->__invoke($input);

        $this->assertSame($expectedOutput, $result);
    }
}
