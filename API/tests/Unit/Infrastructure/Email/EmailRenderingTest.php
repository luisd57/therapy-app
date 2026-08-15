<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Email;

use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Timezone;
use App\Infrastructure\Config\EnvPracticeTimezoneProvider;
use App\Infrastructure\Email\Appointment\AppointmentEmailSender;
use App\Infrastructure\Email\User\SymfonyEmailSender;
use App\Tests\Helper\DomainTestHelper;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email as MimeEmail;

final class EmailRenderingTest extends TestCase
{
    private const FRONTEND_URL = 'http://localhost:4200';
    private const PRACTICE_TIMEZONE = 'America/Caracas';

    private MailerInterface&MockObject $mailer;
    private ?MimeEmail $sentEmail = null;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->mailer->method('send')->willReturnCallback(function (object $message): void {
            $this->sentEmail = $message instanceof MimeEmail ? $message : null;
        });
    }

    public function testInvitationEmailContainsRegistrationButton(): void
    {
        $sender = new SymfonyEmailSender($this->mailer, self::FRONTEND_URL);
        $url = self::FRONTEND_URL . '/register?token=abc123';

        $sender->sendInvitation(Email::fromString('new@example.com'), 'New Patient', $url);

        $this->assertNotNull($this->sentEmail);
        $this->assertSame('You have been invited to join Therapy App', $this->sentEmail->getSubject());
        $html = $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('href="' . $url . '"', $html);
        $this->assertStringContainsString('>Complete Registration</a>', $html);
        $this->assertStringContainsString($url, $this->sentEmail->getTextBody());
    }

    public function testPasswordResetEmailContainsResetButton(): void
    {
        $sender = new SymfonyEmailSender($this->mailer, self::FRONTEND_URL);
        $url = self::FRONTEND_URL . '/reset-password?token=xyz789';

        $sender->sendPasswordReset(Email::fromString('user@example.com'), $url);

        $this->assertNotNull($this->sentEmail);
        $this->assertSame('Password Reset Request', $this->sentEmail->getSubject());
        $html = $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('href="' . $url . '"', $html);
        $this->assertStringContainsString('>Reset Password</a>', $html);
        $this->assertStringContainsString($url, $this->sentEmail->getTextBody());
    }

    public function testWelcomeEmailContainsLoginButton(): void
    {
        $sender = new SymfonyEmailSender($this->mailer, self::FRONTEND_URL);

        $sender->sendWelcome(Email::fromString('patient@example.com'), 'Jane Doe');

        $this->assertNotNull($this->sentEmail);
        $this->assertSame('Welcome to Therapy App', $this->sentEmail->getSubject());
        $html = $this->sentEmail->getHtmlBody();
        $expectedUrl = self::FRONTEND_URL . '/patient-login';
        $this->assertStringContainsString('href="' . $expectedUrl . '"', $html);
        $this->assertStringContainsString('>Log in</a>', $html);
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString($expectedUrl, $this->sentEmail->getTextBody());
    }

    public function testRequestAcknowledgmentCarriesRequesterNameAndModality(): void
    {
        $sender = $this->appointmentSender();

        $sender->sendRequestAcknowledgment(
            Email::fromString('requester@example.com'),
            'John Smith',
            self::instant(),
            AppointmentModality::ONLINE,
            Timezone::fromString('Europe/Madrid'),
        );

        $this->assertNotNull($this->sentEmail);
        $this->assertSame('Your Appointment Request Has Been Received', $this->sentEmail->getSubject());
        $html = (string) $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('John Smith', $html);
        $this->assertStringContainsString('Online', $html);
    }

    public function testRequestAcknowledgmentRendersTimeInRequesterZone(): void
    {
        $sender = $this->appointmentSender();

        $sender->sendRequestAcknowledgment(
            Email::fromString('requester@example.com'),
            'John Smith',
            self::instant(),
            AppointmentModality::ONLINE,
            Timezone::fromString('Europe/Madrid'),
        );

        $this->assertNotNull($this->sentEmail);
        $html = (string) $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('Monday, June 15, 2026', $html);
        $this->assertStringContainsString('4:30 PM (Madrid)', $html);
        $this->assertStringNotContainsString('2:30 PM', $html);
    }

    public function testRequestAcknowledgmentFallsBackToPracticeZoneWithoutRequesterZone(): void
    {
        $sender = $this->appointmentSender();

        $sender->sendRequestAcknowledgment(
            Email::fromString('requester@example.com'),
            'John Smith',
            self::instant(),
            AppointmentModality::ONLINE,
            null,
        );

        $this->assertNotNull($this->sentEmail);
        $html = (string) $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('10:30 AM (Caracas)', $html);
        // Both parties read the same clock here, so a second line would only repeat it.
        $this->assertStringNotContainsString("Therapist's time", $html);
    }

    public function testRequestAcknowledgmentShowsTherapistTimeAsSecondaryLine(): void
    {
        $sender = $this->appointmentSender();

        $sender->sendRequestAcknowledgment(
            Email::fromString('requester@example.com'),
            'John Smith',
            self::instant(),
            AppointmentModality::ONLINE,
            Timezone::fromString('Europe/Madrid'),
        );

        $this->assertNotNull($this->sentEmail);
        $html = (string) $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString(
            "Therapist's time: Monday, June 15, 2026 at 10:30 AM (Caracas)",
            $html,
        );
        $this->assertStringContainsString(
            "Therapist's time: Monday, June 15, 2026 at 10:30 AM (Caracas)",
            (string) $this->sentEmail->getTextBody(),
        );
    }

    public function testTherapistNewRequestAlertContainsDashboardButton(): void
    {
        $sender = $this->appointmentSender();

        $sender->sendNewRequestAlertToTherapist(
            Email::fromString('therapist@example.com'),
            'John Smith',
            self::instant(),
            AppointmentModality::IN_PERSON,
            Timezone::fromString('Europe/Madrid'),
        );

        $this->assertNotNull($this->sentEmail);
        $this->assertSame('New Appointment Request', $this->sentEmail->getSubject());
        $html = $this->sentEmail->getHtmlBody();
        $expectedUrl = self::FRONTEND_URL . '/login';
        $this->assertStringContainsString('href="' . $expectedUrl . '"', $html);
        $this->assertStringContainsString('>Open Dashboard</a>', $html);
        $this->assertStringContainsString('John Smith', $html);
        $this->assertStringContainsString($expectedUrl, $this->sentEmail->getTextBody());
    }

    public function testTherapistNewRequestAlertRendersPracticeZoneAndRequesterTime(): void
    {
        $sender = $this->appointmentSender();

        $sender->sendNewRequestAlertToTherapist(
            Email::fromString('therapist@example.com'),
            'John Smith',
            self::instant(),
            AppointmentModality::IN_PERSON,
            Timezone::fromString('Europe/Madrid'),
        );

        $this->assertNotNull($this->sentEmail);
        $html = (string) $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('10:30 AM (Caracas)', $html);
        $this->assertStringContainsString(
            "Requester's time: Monday, June 15, 2026 at 4:30 PM (Madrid)",
            $html,
        );
        $this->assertStringNotContainsString('2:30 PM', $html);
    }

    public function testTherapistNewRequestAlertFallsBackToPracticeZoneWithoutRequesterZone(): void
    {
        $sender = $this->appointmentSender();

        $sender->sendNewRequestAlertToTherapist(
            Email::fromString('therapist@example.com'),
            'John Smith',
            self::instant(),
            AppointmentModality::IN_PERSON,
            null,
        );

        $this->assertNotNull($this->sentEmail);
        $html = (string) $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('10:30 AM (Caracas)', $html);
        $this->assertStringNotContainsString("Requester's time", $html);
    }

    public function testConfirmationEmailSentWithCorrectDetails(): void
    {
        $sender = $this->appointmentSender();

        $sender->sendConfirmationToPatient(
            Email::fromString('patient@example.com'),
            'Jane Doe',
            self::instant(),
            AppointmentModality::ONLINE,
            Timezone::fromString('Europe/Madrid'),
        );

        $this->assertNotNull($this->sentEmail);
        $this->assertSame('Your Appointment Has Been Confirmed', $this->sentEmail->getSubject());
        $html = $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString('Appointment Confirmed', $html);
    }

    public function testCancellationEmailSentWithCorrectDetails(): void
    {
        $sender = $this->appointmentSender();

        $sender->sendCancellationToPatient(
            Email::fromString('patient@example.com'),
            'Jane Doe',
            self::instant(),
            AppointmentModality::ONLINE,
            Timezone::fromString('Europe/Madrid'),
        );

        $this->assertNotNull($this->sentEmail);
        $this->assertSame('Your Appointment Has Been Cancelled', $this->sentEmail->getSubject());
        $html = $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString('Appointment Cancelled', $html);
    }

    public function testConfirmationRendersRequesterZoneAndTherapistTime(): void
    {
        $sender = $this->appointmentSender();

        $sender->sendConfirmationToPatient(
            Email::fromString('patient@example.com'),
            'Jane Doe',
            self::instant(),
            AppointmentModality::ONLINE,
            Timezone::fromString('Europe/Madrid'),
        );

        $this->assertNotNull($this->sentEmail);
        $html = (string) $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('4:30 PM (Madrid)', $html);
        $this->assertStringContainsString(
            "Therapist's time: Monday, June 15, 2026 at 10:30 AM (Caracas)",
            $html,
        );
        $this->assertStringNotContainsString('2:30 PM', $html);
    }

    public function testCancellationRendersRequesterZoneAndTherapistTime(): void
    {
        $sender = $this->appointmentSender();

        $sender->sendCancellationToPatient(
            Email::fromString('patient@example.com'),
            'Jane Doe',
            self::instant(),
            AppointmentModality::ONLINE,
            Timezone::fromString('Europe/Madrid'),
        );

        $this->assertNotNull($this->sentEmail);
        $html = (string) $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('4:30 PM (Madrid)', $html);
        $this->assertStringContainsString(
            "Therapist's time: Monday, June 15, 2026 at 10:30 AM (Caracas)",
            $html,
        );
        $this->assertStringNotContainsString('2:30 PM', $html);
    }

    public function testCancellationFallsBackToPracticeZoneWithoutRequesterZone(): void
    {
        $sender = $this->appointmentSender();

        $sender->sendCancellationToPatient(
            Email::fromString('patient@example.com'),
            'Jane Doe',
            self::instant(),
            AppointmentModality::ONLINE,
            null,
        );

        $this->assertNotNull($this->sentEmail);
        $html = (string) $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('10:30 AM (Caracas)', $html);
        $this->assertStringNotContainsString("Therapist's time", $html);
    }

    public function testDailyAgendaEmailContainsDashboardButtonAndTable(): void
    {
        $sender = $this->appointmentSender();
        $date = new DateTimeImmutable('2026-06-15 00:00:00');

        $appointments = new ArrayCollection([
            DomainTestHelper::createConfirmedAppointment(
                startTime: new DateTimeImmutable('2026-06-15 10:00:00'),
                fullName: 'Alice Patient',
            ),
            DomainTestHelper::createConfirmedAppointment(
                startTime: new DateTimeImmutable('2026-06-15 11:00:00'),
                fullName: 'Bob Patient',
            ),
        ]);

        $sender->sendDailyAgendaToTherapist(
            Email::fromString('therapist@example.com'),
            'Dr. Therapist',
            $date,
            $appointments,
        );

        $this->assertNotNull($this->sentEmail);
        $this->assertStringContainsString('Daily Agenda', (string) $this->sentEmail->getSubject());
        $html = $this->sentEmail->getHtmlBody();
        $expectedUrl = self::FRONTEND_URL . '/login';
        $this->assertStringContainsString('href="' . $expectedUrl . '"', $html);
        $this->assertStringContainsString('>Open Dashboard</a>', $html);
        $this->assertStringContainsString('Alice Patient', $html);
        $this->assertStringContainsString('Bob Patient', $html);
        $this->assertStringContainsString('2 confirmed appointments', $html);
        $this->assertStringContainsString($expectedUrl, $this->sentEmail->getTextBody());
    }

    public function testDailyAgendaRendersPracticeZoneAndEachPatientsTime(): void
    {
        $sender = $this->appointmentSender();
        // 16 June 00:00 UTC is still 15 June in Caracas, which is the day the
        // therapist is being sent.
        $date = new DateTimeImmutable('2026-06-16T00:00:00+00:00');

        $appointments = new ArrayCollection([
            DomainTestHelper::createConfirmedAppointment(
                startTime: self::instant(),
                fullName: 'Alice Patient',
                requesterTimezone: Timezone::fromString('Europe/Madrid'),
            ),
            DomainTestHelper::createConfirmedAppointment(
                startTime: new DateTimeImmutable('2026-06-15T16:00:00+00:00'),
                fullName: 'Bob Patient',
            ),
        ]);

        $sender->sendDailyAgendaToTherapist(
            Email::fromString('therapist@example.com'),
            'Dr. Therapist',
            $date,
            $appointments,
        );

        $this->assertNotNull($this->sentEmail);
        $html = (string) $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('Monday, June 15, 2026', $html);
        $this->assertStringContainsString('Time (Caracas)', $html);
        $this->assertStringContainsString('>10:30 AM<', $html);
        $this->assertStringContainsString('>12:00 PM<', $html);
        $this->assertStringContainsString('4:30 PM (Madrid)', $html);
        $this->assertStringNotContainsString('2:30 PM', $html);
        $text = (string) $this->sentEmail->getTextBody();
        $this->assertStringContainsString('10:30 AM (Caracas) | patient: 4:30 PM (Madrid)', $text);
        // Bob keeps the practice clock, so his line carries no second time.
        $this->assertStringContainsString("12:00 PM (Caracas) | Bob Patient", $text);
    }

    public function testDailyAgendaEmailHandlesEmptyAppointmentList(): void
    {
        $sender = $this->appointmentSender();
        $date = new DateTimeImmutable('2026-06-15 00:00:00');

        $sender->sendDailyAgendaToTherapist(
            Email::fromString('therapist@example.com'),
            'Dr. Therapist',
            $date,
            new ArrayCollection(),
        );

        $this->assertNotNull($this->sentEmail);
        $html = $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('No confirmed appointments for today', $html);
        $expectedUrl = self::FRONTEND_URL . '/login';
        $this->assertStringContainsString('href="' . $expectedUrl . '"', $html);
    }

    /**
     * 14:30 UTC on 15 June 2026: 10:30 in Caracas (UTC-4 year round) and 16:30
     * in Madrid (UTC+2 in summer). Both times are hand-derived, never formatted
     * from this instant - see ADR-0003.
     */
    private static function instant(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-06-15T14:30:00+00:00');
    }

    private function appointmentSender(): AppointmentEmailSender
    {
        return new AppointmentEmailSender(
            $this->mailer,
            new EnvPracticeTimezoneProvider(self::PRACTICE_TIMEZONE),
            self::FRONTEND_URL,
        );
    }
}
