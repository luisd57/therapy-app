<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\BookAppointmentInputDTO;
use App\Application\Appointment\Handler\BookAppointmentHandler;
use App\Application\Appointment\Service\SlotGenerationRulesFactory;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\User\Id\UserId;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\Config\EnvPracticeTimezoneProvider;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\UsesUtcInstants;
use Symfony\Component\Clock\ClockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class BookAppointmentHandlerTest extends TestCase
{
    use UsesUtcInstants;

    private const string PATIENT_ID = '019525f3-5be1-7190-a6e1-aaa000000001';

    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private ClockInterface&MockObject $clock;
    private BookAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(self::utc('2026-06-15 12:00:00'));

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('getByIdOrFail')->willReturn(
            DomainTestHelper::createActivePatient(id: UserId::fromString(self::PATIENT_ID)),
        );

        $this->handler = new BookAppointmentHandler(
            appointmentRepository: $this->appointmentRepository,
            userRepository: $userRepository,
            clock: $this->clock,
            slotGenerationRulesFactory: new SlotGenerationRulesFactory(
                practiceTimezoneProvider: new EnvPracticeTimezoneProvider('America/Caracas'),
                appointmentDurationMinutes: 50,
                slotStartIncrementMinutes: 30,
            ),
        );
    }

    public function testBookCreatesConfirmedAppointment(): void
    {
        $this->appointmentRepository
            ->expects($this->once())
            ->method('save');

        $result = $this->handler->__invoke(new BookAppointmentInputDTO(
            slotStartTime: '2026-04-01T10:00:00+00:00',
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
        $this->assertSame('2026-04-01T10:00:00+00:00', $result->startTime);
        $this->assertSame('2026-04-01T10:50:00+00:00', $result->endTime);
        $this->assertSame('2026-06-15T12:00:00+00:00', $result->createdAt);
        $this->assertSame('2026-06-15T12:00:00+00:00', $result->updatedAt);
    }

    public function testBookWithPatientId(): void
    {
        $this->appointmentRepository
            ->expects($this->once())
            ->method('save');

        $result = $this->handler->__invoke(new BookAppointmentInputDTO(
            slotStartTime: '2026-04-01T10:00:00+00:00',
            modality: 'IN_PERSON',
            fullName: 'Jane Smith',
            phone: '+9876543210',
            email: 'jane@example.com',
            city: 'Los Angeles',
            country: 'USA',
            patientId: self::PATIENT_ID,
        ));

        $this->assertSame('CONFIRMED', $result->status);
        $this->assertSame(self::PATIENT_ID, $result->patientId);
    }
}
