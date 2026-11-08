<?php

declare(strict_types=1);

namespace App\Domain\Identity\Notifications;

use App\Domain\Identity\Mail\VerifyEmailMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $name,
        public string $otp,
        public int $expiresInMinutes = 15
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): Mailable
    {
        $destination = method_exists($notifiable, 'routeNotificationFor')
            ? ($notifiable->routeNotificationFor('mail') ?? $notifiable->email ?? null)
            : ($notifiable->email ?? ($notifiable->routes['mail'] ?? null));

        $mail = new VerifyEmailMailable($this->name, $this->otp, $this->expiresInMinutes);

        return $destination ? $mail->to($destination) : $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'email_verification',
            'expires_in_minutes' => $this->expiresInMinutes,
        ];
    }
}
