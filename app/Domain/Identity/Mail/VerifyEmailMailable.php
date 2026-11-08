<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $otp,
        public int $expiresInMinutes = 15
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Email Address - '.config('app.name', 'LiveBids'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.verify_email',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
