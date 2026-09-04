<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class InvoiceOverdueReminderMail extends CuciNowMailable
{
    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return $this->brandedEnvelope(
            "Payment reminder: {$this->invoice->invoice_number}",
            ['category' => 'invoice_reminder', 'invoice_id' => $this->invoice->id],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invoice-overdue-reminder');
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("invoice-reminder-{$this->invoice->id}-".now()->format('Ymd'));
    }
}
