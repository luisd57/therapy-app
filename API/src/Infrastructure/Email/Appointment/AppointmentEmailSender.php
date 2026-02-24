<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\Appointment;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Service\AppointmentEmailSenderInterface;
use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\User\ValueObject\Email;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
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
        $fullName = htmlspecialchars($fullName, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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
        $requesterName = htmlspecialchars($requesterName, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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

    public function sendConfirmationToPatient(
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
            ->subject('Your Appointment Has Been Confirmed')
            ->html($this->getConfirmationTemplate($fullName, $formattedDate, $formattedTime, $modalityLabel))
            ->text($this->getConfirmationTextTemplate($fullName, $formattedDate, $formattedTime, $modalityLabel));

        $this->mailer->send($email);
    }

    public function sendCancellationToPatient(
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
            ->subject('Your Appointment Has Been Cancelled')
            ->html($this->getCancellationTemplate($fullName, $formattedDate, $formattedTime, $modalityLabel))
            ->text($this->getCancellationTextTemplate($fullName, $formattedDate, $formattedTime, $modalityLabel));

        $this->mailer->send($email);
    }

    private function getConfirmationTemplate(
        string $fullName,
        string $date,
        string $time,
        string $modality,
    ): string {
        $fullName = htmlspecialchars($fullName, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .details { background-color: #e8f5e9; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Appointment Confirmed</h1>
        <p>Dear {$fullName},</p>
        <p>Your appointment has been confirmed. Please see the details below.</p>
        <div class="details">
            <p><strong>Date:</strong> {$date}</p>
            <p><strong>Time:</strong> {$time}</p>
            <p><strong>Modality:</strong> {$modality}</p>
        </div>
        <p>If you need to make any changes, please contact us as soon as possible.</p>
        <div class="footer">
            <p>Thank you for choosing our services.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getConfirmationTextTemplate(
        string $fullName,
        string $date,
        string $time,
        string $modality,
    ): string {
        return <<<TEXT
Appointment Confirmed

Dear {$fullName},

Your appointment has been confirmed. Please see the details below.

Appointment Details:
- Date: {$date}
- Time: {$time}
- Modality: {$modality}

If you need to make any changes, please contact us as soon as possible.

Thank you for choosing our services.
TEXT;
    }

    private function getCancellationTemplate(
        string $fullName,
        string $date,
        string $time,
        string $modality,
    ): string {
        $fullName = htmlspecialchars($fullName, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .details { background-color: #ffebee; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Appointment Cancelled</h1>
        <p>Dear {$fullName},</p>
        <p>We regret to inform you that your appointment has been cancelled.</p>
        <div class="details">
            <p><strong>Date:</strong> {$date}</p>
            <p><strong>Time:</strong> {$time}</p>
            <p><strong>Modality:</strong> {$modality}</p>
        </div>
        <p>If you have any questions or would like to schedule a new appointment, please don't hesitate to reach out.</p>
        <div class="footer">
            <p>We apologize for any inconvenience.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getCancellationTextTemplate(
        string $fullName,
        string $date,
        string $time,
        string $modality,
    ): string {
        return <<<TEXT
Appointment Cancelled

Dear {$fullName},

We regret to inform you that your appointment has been cancelled.

Appointment Details:
- Date: {$date}
- Time: {$time}
- Modality: {$modality}

If you have any questions or would like to schedule a new appointment, please don't hesitate to reach out.

We apologize for any inconvenience.
TEXT;
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

    /**
     * @param ArrayCollection<int, Appointment> $appointments
     */
    public function sendDailyAgendaToTherapist(
        Email $therapistEmail,
        string $therapistName,
        DateTimeImmutable $date,
        ArrayCollection $appointments,
    ): void {
        $formattedDate = $date->format('l, F j, Y');

        $email = (new MimeEmail())
            ->from("{$this->fromName} <{$this->fromEmail}>")
            ->to($therapistEmail->getValue())
            ->subject("Daily Agenda — {$formattedDate}")
            ->html($this->getDailyAgendaTemplate($therapistName, $formattedDate, $appointments))
            ->text($this->getDailyAgendaTextTemplate($therapistName, $formattedDate, $appointments));

        $this->mailer->send($email);
    }

    /**
     * @param ArrayCollection<int, Appointment> $appointments
     */
    private function getDailyAgendaTemplate(
        string $therapistName,
        string $formattedDate,
        ArrayCollection $appointments,
    ): string {
        $appointmentCount = $appointments->count();

        if ($appointmentCount === 0) {
            $tableHtml = '<p style="color: #666; font-style: italic;">No confirmed appointments for today.</p>';
        } else {
            $rows = '';
            foreach ($appointments as $appointment) {
                $time = $appointment->getTimeSlot()->getStartTime()->format('g:i A');
                $name = htmlspecialchars($appointment->getFullName(), ENT_QUOTES, 'UTF-8');
                $modality = $appointment->getModality()->getDisplayName();
                $phone = htmlspecialchars($appointment->getPhone()->getValue(), ENT_QUOTES, 'UTF-8');
                $payment = $appointment->isPaymentVerified() ? 'Verified' : 'Pending';
                $paymentColor = $appointment->isPaymentVerified() ? '#2e7d32' : '#e65100';

                $rows .= <<<HTML
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">{$time}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">{$name}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">{$modality}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">{$phone}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee; color: {$paymentColor}; font-weight: bold;">{$payment}</td>
                    </tr>
                HTML;
            }

            $tableHtml = <<<HTML
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #f5f5f5;">
                            <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Time</th>
                            <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Patient</th>
                            <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Modality</th>
                            <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Phone</th>
                            <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$rows}
                    </tbody>
                </table>
            HTML;
        }

        $summary = $appointmentCount === 1
            ? '1 confirmed appointment'
            : "{$appointmentCount} confirmed appointments";

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background-color: #1565c0; color: white; padding: 20px; border-radius: 4px 4px 0 0; }
        .content { padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 4px 4px; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">Daily Agenda</h1>
            <p style="margin: 5px 0 0;">{$formattedDate}</p>
        </div>
        <div class="content">
            <p>Good morning, {$therapistName}!</p>
            <p>You have <strong>{$summary}</strong> for today.</p>
            {$tableHtml}
        </div>
        <div class="footer">
            <p>This is an automated daily agenda summary from Therapy App.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * @param ArrayCollection<int, Appointment> $appointments
     */
    private function getDailyAgendaTextTemplate(
        string $therapistName,
        string $formattedDate,
        ArrayCollection $appointments,
    ): string {
        $appointmentCount = $appointments->count();
        $summary = $appointmentCount === 1
            ? '1 confirmed appointment'
            : "{$appointmentCount} confirmed appointments";

        if ($appointmentCount === 0) {
            $listText = 'No confirmed appointments for today.';
        } else {
            $lines = [];
            foreach ($appointments as $appointment) {
                $time = $appointment->getTimeSlot()->getStartTime()->format('g:i A');
                $name = $appointment->getFullName();
                $modality = $appointment->getModality()->getDisplayName();
                $phone = $appointment->getPhone()->getValue();
                $payment = $appointment->isPaymentVerified() ? 'Verified' : 'Pending';

                $lines[] = "- {$time} | {$name} | {$modality} | {$phone} | Payment: {$payment}";
            }
            $listText = implode("\n", $lines);
        }

        return <<<TEXT
Daily Agenda — {$formattedDate}

Good morning, {$therapistName}!

You have {$summary} for today.

{$listText}

---
This is an automated daily agenda summary from Therapy App.
TEXT;
    }
}
