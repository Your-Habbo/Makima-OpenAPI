<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $code
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Two-Factor Authentication Code')
            ->greeting('Hello!')
            ->line('Your two-factor authentication code is: **' . $this->code . '**')
            ->line('This code will expire in 10 minutes.')
            ->line('If you did not request this code, please secure your account immediately.')
            ->salutation('Regards, ' . config('app.name'));
    }
}