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

class SendRegisterCaptcha extends Notification implements ShouldQueue
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
        $notice = __('We have received your request for a verification code. Below is your code. Please verify it as soon as possible. The code is valid for 10 minutes. To ensure the security of your account, do not disclose this code to anyone. Our staff will never ask you for this code.');
        $warning = __('For security reasons, please do not share the verification code you received with anyone. If you did not request a password reset, please ignore this email. If you have any concerns, please contact customer support');
        return (new SendEmailCaptcha($this->captcha, $warning, $notice))->subject('Email verification code')->to($to);
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
