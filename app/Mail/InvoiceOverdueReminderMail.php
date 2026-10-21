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
        return $this->brandedContent('emails.invoice-overdue-reminder', implode("\n", [
            "Payment reminder: invoice {$this->invoice->invoice_number}",
            'Our records show a remaining balance that was due on '.$this->invoice->due_at?->format('d M Y').'. If payment has already been made, please disregard this reminder.',
            'Balance due: RM '.number_format($this->invoice->balance, 2),
            'Contact accounts: '.route('whatsapp.redirect', ['text' => 'Hi CuciNow, I am contacting you about payment for invoice '.$this->invoice->invoice_number]),
        ]));
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("invoice-reminder-{$this->invoice->id}-".now()->format('Ymd'));
    }
}
