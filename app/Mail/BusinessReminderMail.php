<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Delivery is performed only by SendScheduledEmailReminder's background job.
class BusinessReminderMail extends Mailable
{
    public function __construct(public readonly array $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->reminder['subject']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.business-reminder');
    }
}
