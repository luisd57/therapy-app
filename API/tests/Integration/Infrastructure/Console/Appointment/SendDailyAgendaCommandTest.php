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
            now: new DateTimeImmutable(),
        );
        $userRepository->save($therapist);
    }

    /**
     * Built fresh each call, never cached: the command takes the clock at
     * construction, so a cached tester would ignore a later freezeClock().
     */
    private function commandTester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:send-daily-agenda'));
    }

    private function saveConfirmedAppointment(string $startTime, string $fullName): void
    {
        $appointment = Appointment::request(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable($startTime), 50),
            modality: AppointmentModality::ONLINE,
            fullName: $fullName,
            email: Email::fromString('agenda@test.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'Test City',
            country: 'US',
            now: new DateTimeImmutable(),
        );
        $appointment->confirm(new DateTimeImmutable());
        $this->appointmentRepository->save($appointment);
    }

    public function testCommandWithNoAppointments(): void
    {
        $tester = $this->commandTester();
        $tester->execute(['date' => '2030-01-01']);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('0 confirmed appointments', $tester->getDisplay());
    }

    public function testCommandWithAppointments(): void
    {
        // 09:00 on the therapist's 10 October. The agenda covers her calendar day,
        // so a naive instant would land on a different day in the process zone.
        $this->saveConfirmedAppointment('2026-10-10T09:00:00-04:00', 'Agenda Patient');

        $tester = $this->commandTester();
        $tester->execute(['date' => '2026-10-10']);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('1 confirmed appointment ', $tester->getDisplay());
    }

    public function testDefaultDateIsTheTherapistsDayNotTheProcessDay(): void
    {
        // 02:00 UTC on 16 June is still 22:00 on 15 June in Caracas, and 16:00 on
        // the 16th in the suite's own Pacific/Kiritimati. Only the practice zone
        // gives the day the therapist is living in.
        $this->freezeClock('2026-06-16 02:00:00');

        $this->saveConfirmedAppointment('2026-06-15T09:00:00-04:00', 'Her Day Patient');
        $this->saveConfirmedAppointment('2026-06-16T09:00:00-04:00', 'Next Day Patient');

        $tester = $this->commandTester();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        $this->assertStringContainsString('2026-06-15', $display);
        $this->assertStringNotContainsString('2026-06-16', $display);
        $this->assertStringContainsString('1 confirmed appointment ', $display);
    }
}
