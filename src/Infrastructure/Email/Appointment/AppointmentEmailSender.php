<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\Appointment;

use App\Domain\Appointment\Service\AppointmentEmailSenderInterface;
use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\User\ValueObject\Email;
use DateTimeImmutable;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email as MimeEmail;

final readonly class AppointmentEmailSender implements AppointmentEmailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail = 'noreply@therapy-app.com',
        private string $fromName = 'Therapy App',
    ) {
    }

    public function sendRequestAcknowledgment(
        Email $to,
        string $fullName,
        DateTimeImmutable $appointmentTime,
        AppointmentModality $modality,
    ): void {
        $formattedDate = $appointmentTime->format('l, F j, Y');
        $formattedTime = $appointmentTime->format('g:i A');
        $modalityLabel = $modality->getDisplayName();

        $email = (new MimeEmail())
            ->from("{$this->fromName} <{$this->fromEmail}>")
            ->to($to->getValue())
            ->subject('Your Appointment Request Has Been Received')
            ->html($this->getAcknowledgmentTemplate($fullName, $formattedDate, $formattedTime, $modalityLabel))
            ->text($this->getAcknowledgmentTextTemplate($fullName, $formattedDate, $formattedTime, $modalityLabel));

        $this->mailer->send($email);
    }

    public function sendNewRequestAlertToTherapist(
        Email $therapistEmail,
        string $requesterName,
        DateTimeImmutable $appointmentTime,
        AppointmentModality $modality,
    ): void {
        $formattedDate = $appointmentTime->format('l, F j, Y');
        $formattedTime = $appointmentTime->format('g:i A');
        $modalityLabel = $modality->getDisplayName();

        $email = (new MimeEmail())
            ->from("{$this->fromName} <{$this->fromEmail}>")
            ->to($therapistEmail->getValue())
            ->subject('New Appointment Request')
            ->html($this->getTherapistAlertTemplate($requesterName, $formattedDate, $formattedTime, $modalityLabel))
            ->text($this->getTherapistAlertTextTemplate($requesterName, $formattedDate, $formattedTime, $modalityLabel));

        $this->mailer->send($email);
    }

    private function getAcknowledgmentTemplate(
        string $fullName,
        string $date,
        string $time,
        string $modality,
    ): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .details { background-color: #f5f5f5; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Appointment Request Received</h1>
        <p>Dear {$fullName},</p>
        <p>Thank you for submitting your appointment request. We have received it and will review it shortly.</p>
        <div class="details">
            <p><strong>Date:</strong> {$date}</p>
            <p><strong>Time:</strong> {$time}</p>
            <p><strong>Modality:</strong> {$modality}</p>
        </div>
        <p>You will receive a confirmation email once your appointment has been reviewed and approved by your therapist.</p>
        <div class="footer">
            <p>If you did not submit this request, please contact us immediately.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getAcknowledgmentTextTemplate(
        string $fullName,
        string $date,
        string $time,
        string $modality,
    ): string {
        return <<<TEXT
Appointment Request Received

Dear {$fullName},

Thank you for submitting your appointment request. We have received it and will review it shortly.

Appointment Details:
- Date: {$date}
- Time: {$time}
- Modality: {$modality}

You will receive a confirmation email once your appointment has been reviewed and approved by your therapist.

If you did not submit this request, please contact us immediately.
TEXT;
    }

    private function getTherapistAlertTemplate(
        string $requesterName,
        string $date,
        string $time,
        string $modality,
    ): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .details { background-color: #f5f5f5; padding: 15px; border-radius: 4px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>New Appointment Request</h1>
        <p>A new appointment request has been submitted and is awaiting your review.</p>
        <div class="details">
            <p><strong>Requester:</strong> {$requesterName}</p>
            <p><strong>Date:</strong> {$date}</p>
            <p><strong>Time:</strong> {$time}</p>
            <p><strong>Modality:</strong> {$modality}</p>
        </div>
        <p>Please log in to your dashboard to review and confirm or decline this request.</p>
    </div>
</body>
</html>
HTML;
    }

    private function getTherapistAlertTextTemplate(
        string $requesterName,
        string $date,
        string $time,
        string $modality,
    ): string {
        return <<<TEXT
New Appointment Request

A new appointment request has been submitted and is awaiting your review.

Request Details:
- Requester: {$requesterName}
- Date: {$date}
- Time: {$time}
- Modality: {$modality}

Please log in to your dashboard to review and confirm or decline this request.
TEXT;
    }
}
