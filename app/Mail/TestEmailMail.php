<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent synchronously from Settings so SMTP errors reach the administrator immediately.
class TestEmailMail extends Mailable
{
    public function __construct(public readonly array $details) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Test email from {$this->details['business_name']}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.test-email');
    }
}
