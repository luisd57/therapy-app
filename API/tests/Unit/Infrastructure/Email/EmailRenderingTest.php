<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Email;

use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\User\ValueObject\Email;
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

    public function testRequestAcknowledgmentEmailSentWithCorrectDetails(): void
    {
        $sender = new AppointmentEmailSender($this->mailer, self::FRONTEND_URL);
        $time = new DateTimeImmutable('2026-06-15 14:30:00');

        $sender->sendRequestAcknowledgment(
            Email::fromString('requester@example.com'),
            'John Smith',
            $time,
            AppointmentModality::ONLINE,
        );

        $this->assertNotNull($this->sentEmail);
        $this->assertSame('Your Appointment Request Has Been Received', $this->sentEmail->getSubject());
        $html = $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('John Smith', $html);
        $this->assertStringContainsString('Monday, June 15, 2026', $html);
        $this->assertStringContainsString('2:30 PM', $html);
    }

    public function testTherapistNewRequestAlertContainsDashboardButton(): void
    {
        $sender = new AppointmentEmailSender($this->mailer, self::FRONTEND_URL);
        $time = new DateTimeImmutable('2026-06-15 14:30:00');

        $sender->sendNewRequestAlertToTherapist(
            Email::fromString('therapist@example.com'),
            'John Smith',
            $time,
            AppointmentModality::IN_PERSON,
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

    public function testConfirmationEmailSentWithCorrectDetails(): void
    {
        $sender = new AppointmentEmailSender($this->mailer, self::FRONTEND_URL);
        $time = new DateTimeImmutable('2026-06-15 14:30:00');

        $sender->sendConfirmationToPatient(
            Email::fromString('patient@example.com'),
            'Jane Doe',
            $time,
            AppointmentModality::ONLINE,
        );

        $this->assertNotNull($this->sentEmail);
        $this->assertSame('Your Appointment Has Been Confirmed', $this->sentEmail->getSubject());
        $html = $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString('Appointment Confirmed', $html);
    }

    public function testCancellationEmailSentWithCorrectDetails(): void
    {
        $sender = new AppointmentEmailSender($this->mailer, self::FRONTEND_URL);
        $time = new DateTimeImmutable('2026-06-15 14:30:00');

        $sender->sendCancellationToPatient(
            Email::fromString('patient@example.com'),
            'Jane Doe',
            $time,
            AppointmentModality::ONLINE,
        );

        $this->assertNotNull($this->sentEmail);
        $this->assertSame('Your Appointment Has Been Cancelled', $this->sentEmail->getSubject());
        $html = $this->sentEmail->getHtmlBody();
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString('Appointment Cancelled', $html);
    }

    public function testDailyAgendaEmailContainsDashboardButtonAndTable(): void
    {
        $sender = new AppointmentEmailSender($this->mailer, self::FRONTEND_URL);
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

    public function testDailyAgendaEmailHandlesEmptyAppointmentList(): void
    {
        $sender = new AppointmentEmailSender($this->mailer, self::FRONTEND_URL);
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
}
