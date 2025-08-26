<?php

namespace App\Notifications;

use App\Mail\RegisterEmail;
use App\Mail\SendEmailCaptcha;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendRegisterCaptcha extends Notification #implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $captcha)
    {
        $this->afterCommit();
    }

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
        $to = '';
        if ($notifiable instanceof AnonymousNotifiable) {
            $to = $notifiable->routes['mail'];
        } else {
            $to = $notifiable->email;
        }
        return (new SendEmailCaptcha($this->captcha))->subject('E-Mail-Bestätigungscode')->to($to);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
