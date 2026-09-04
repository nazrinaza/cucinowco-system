<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class InvoiceMail extends CuciNowMailable
{
    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return $this->brandedEnvelope(
            "CuciNow invoice {$this->invoice->invoice_number}",
            ['category' => 'invoice', 'invoice_id' => $this->invoice->id],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invoice');
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("invoice-{$this->invoice->id}-{$this->invoice->updated_at->timestamp}");
    }
}
