<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Console\Appointment;

use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Id\AppointmentId;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\Id\UserId;
use App\Domain\Appointment\Entity\Appointment;
use App\Tests\Helper\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use DateTimeImmutable;

final class SendDailyAgendaCommandTest extends IntegrationTestCase
{
    private CommandTester $commandTester;
    private AppointmentRepositoryInterface $appointmentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appointmentRepository = self::getContainer()->get(AppointmentRepositoryInterface::class);

        // Ensure a therapist exists in the system
        $userRepository = self::getContainer()->get(UserRepositoryInterface::class);
        $therapist = User::createTherapist(
            id: UserId::generate(),
            email: Email::fromString('therapist-cmd@test.com'),
            fullName: 'Dr. Command Test',
            hashedPassword: 'hashed_password',
        );
        $userRepository->save($therapist);

        $application = new Application(self::$kernel);
        $command = $application->find('app:send-daily-agenda');
        $this->commandTester = new CommandTester($command);
    }

    public function testCommandWithNoAppointments(): void
    {
        $this->commandTester->execute(['date' => '2030-01-01']);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('0 confirmed appointments', $this->commandTester->getDisplay());
    }

    public function testCommandWithAppointments(): void
    {
        $appointment = Appointment::request(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('2026-10-10 09:00:00'), 50),
            modality: AppointmentModality::ONLINE,
            fullName: 'Agenda Patient',
            email: Email::fromString('agenda@test.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'Test City',
            country: 'US',
        );
        $appointment->confirm();
        $this->appointmentRepository->save($appointment);

        $this->commandTester->execute(['date' => '2026-10-10']);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('1 confirmed appointment ', $this->commandTester->getDisplay());
    }

    public function testCommandDefaultsToToday(): void
    {
        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        $today = date('Y-m-d');
        $this->assertStringContainsString($today, $this->commandTester->getDisplay());
    }
}
