<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Carries the one-time email-verification link. The token itself only exists
 * in plaintext inside this message; the database stores a SHA-256 only. The
 * link points at the SPA (`/verify-email`) which forwards the token to the
 * API, so the flow works identically in the local log driver and — once an
 * SMTP transport is configured — in real delivery.
 */
class VerifyEmailNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $verifyUrl,
        public readonly int $expiresInMinutes,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('auth.verify_email_subject'))
            ->greeting(__('auth.verify_email_greeting', ['name' => $notifiable->name]))
            ->line(__('auth.verify_email_line'))
            ->action(__('auth.verify_email_action'), $this->verifyUrl)
            ->line(__('auth.verify_email_footer', ['minutes' => $this->expiresInMinutes]));
    }
}
