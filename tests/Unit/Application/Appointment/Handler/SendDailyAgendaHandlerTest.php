<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\SendDailyAgendaInputDTO;
use App\Application\Appointment\Handler\SendDailyAgendaHandler;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Service\AppointmentEmailSenderInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SendDailyAgendaHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private AppointmentEmailSenderInterface&MockObject $emailSender;
    private SendDailyAgendaHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->emailSender = $this->createMock(AppointmentEmailSenderInterface::class);
        $this->handler = new SendDailyAgendaHandler(
            $this->userRepository,
            $this->appointmentRepository,
            $this->emailSender,
        );
    }

    public function testSendAgendaWithAppointments(): void
    {
        $therapist = DomainTestHelper::createTherapist();
        $appointment1 = DomainTestHelper::createConfirmedAppointment(
            startTime: new \DateTimeImmutable('2026-06-01 09:00:00'),
            fullName: 'Alice',
            email: 'alice@test.com',
        );
        $appointment2 = DomainTestHelper::createConfirmedAppointment(
            startTime: new \DateTimeImmutable('2026-06-01 10:00:00'),
            fullName: 'Bob',
            email: 'bob@test.com',
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findSingleTherapist')
            ->willReturn($therapist);

        $this->appointmentRepository
            ->expects($this->once())
            ->method('findConfirmedByDate')
            ->willReturn(new ArrayCollection([$appointment1, $appointment2]));

        $this->emailSender
            ->expects($this->once())
            ->method('sendDailyAgendaToTherapist');

        $count = $this->handler->__invoke(new SendDailyAgendaInputDTO(date: '2026-06-01'));

        $this->assertSame(2, $count);
    }

    public function testSendAgendaWithNoAppointments(): void
    {
        $therapist = DomainTestHelper::createTherapist();

        $this->userRepository
            ->expects($this->once())
            ->method('findSingleTherapist')
            ->willReturn($therapist);

        $this->appointmentRepository
            ->expects($this->once())
            ->method('findConfirmedByDate')
            ->willReturn(new ArrayCollection());

        $this->emailSender
            ->expects($this->once())
            ->method('sendDailyAgendaToTherapist');

        $count = $this->handler->__invoke(new SendDailyAgendaInputDTO(date: '2026-06-01'));

        $this->assertSame(0, $count);
    }
}
