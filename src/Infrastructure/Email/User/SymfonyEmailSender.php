<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\User;

use App\Domain\User\Service\EmailSenderInterface;
use App\Domain\User\ValueObject\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email as MimeEmail;

final readonly class SymfonyEmailSender implements EmailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail = 'noreply@therapy-app.com',
        private string $fromName = 'Therapy App',
    ) {
    }

    public function sendInvitation(Email $to, string $patientName, string $registrationUrl): void
    {
        $email = (new MimeEmail())
            ->from("{$this->fromName} <{$this->fromEmail}>")
            ->to($to->getValue())
            ->subject('You have been invited to join Therapy App')
            ->html($this->getInvitationTemplate($patientName, $registrationUrl))
            ->text($this->getInvitationTextTemplate($patientName, $registrationUrl));

        $this->mailer->send($email);
    }

    public function sendPasswordReset(Email $to, string $resetUrl): void
    {
        $email = (new MimeEmail())
            ->from("{$this->fromName} <{$this->fromEmail}>")
            ->to($to->getValue())
            ->subject('Password Reset Request')
            ->html($this->getPasswordResetTemplate($resetUrl))
            ->text($this->getPasswordResetTextTemplate($resetUrl));

        $this->mailer->send($email);
    }

    public function sendWelcome(Email $to, string $userName): void
    {
        $email = (new MimeEmail())
            ->from("{$this->fromName} <{$this->fromEmail}>")
            ->to($to->getValue())
            ->subject('Welcome to Therapy App')
            ->html($this->getWelcomeTemplate($userName))
            ->text($this->getWelcomeTextTemplate($userName));

        $this->mailer->send($email);
    }

    private function getInvitationTemplate(string $patientName, string $registrationUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome, {$patientName}!</h1>
        <p>You have been invited to join our therapy platform. Please click the button below to complete your registration.</p>
        <p><a href="{$registrationUrl}" class="button">Complete Registration</a></p>
        <p>If the button doesn't work, copy and paste this link into your browser:</p>
        <p>{$registrationUrl}</p>
        <p>This invitation link will expire in 24 hours.</p>
        <div class="footer">
            <p>If you did not expect this invitation, please ignore this email.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getInvitationTextTemplate(string $patientName, string $registrationUrl): string
    {
        return <<<TEXT
Welcome, {$patientName}!

You have been invited to join our therapy platform. Please visit the link below to complete your registration:

{$registrationUrl}

This invitation link will expire in 24 hours.

If you did not expect this invitation, please ignore this email.
TEXT;
    }

    private function getPasswordResetTemplate(string $resetUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Password Reset Request</h1>
        <p>We received a request to reset your password. Click the button below to create a new password:</p>
        <p><a href="{$resetUrl}" class="button">Reset Password</a></p>
        <p>If the button doesn't work, copy and paste this link into your browser:</p>
        <p>{$resetUrl}</p>
        <p>This link will expire in 1 hour.</p>
        <div class="footer">
            <p>If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getPasswordResetTextTemplate(string $resetUrl): string
    {
        return <<<TEXT
Password Reset Request

We received a request to reset your password. Visit the link below to create a new password:

{$resetUrl}

This link will expire in 1 hour.

If you did not request a password reset, please ignore this email. Your password will remain unchanged.
TEXT;
    }

    private function getWelcomeTemplate(string $userName): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to Therapy App, {$userName}!</h1>
        <p>Your account has been successfully created. You can now log in and access your dashboard.</p>
        <p>If you have any questions, please don't hesitate to reach out to your therapist.</p>
        <p>Best regards,<br>The Therapy App Team</p>
    </div>
</body>
</html>
HTML;
    }

    private function getWelcomeTextTemplate(string $userName): string
    {
        return <<<TEXT
Welcome to Therapy App, {$userName}!

Your account has been successfully created. You can now log in and access your dashboard.

If you have any questions, please don't hesitate to reach out to your therapist.

Best regards,
The Therapy App Team
TEXT;
    }
}
