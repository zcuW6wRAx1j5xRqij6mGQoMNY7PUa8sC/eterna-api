<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendEmailCaptcha extends Mailable
{
    use Queueable;

    /**
     * Create a new message instance.
     */
    public function __construct(public string $code,public string $warning, public string $notice )
    {

    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            // view: 'emails.captcha',
            text: 'emails.captcha-text'
        );
    }
}
