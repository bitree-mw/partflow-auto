<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $alert) {}

    public function envelope(): Envelope
    {
        $status = $this->alert['available_quantity'] === 0 ? 'Out of stock' : 'Low stock';

        return new Envelope(
            subject: "{$status}: {$this->alert['product_code']} at {$this->alert['site_name']}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.low-stock-alert');
    }
}
