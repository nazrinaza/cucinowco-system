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
        $items = $this->invoice->items
            ->map(fn ($item): string => "- {$item->description}: RM ".number_format($item->amount, 2))
            ->implode("\n");

        return $this->brandedContent('emails.invoice', implode("\n", [
            "Invoice {$this->invoice->invoice_number}",
            'Issued: '.$this->invoice->issued_at?->format('d M Y'),
            'Due: '.$this->invoice->due_at?->format('d M Y'),
            '',
            $items,
            '',
            'Total: RM '.number_format($this->invoice->total, 2),
            'Balance due: RM '.number_format($this->invoice->balance, 2),
            "Payment terms: {$this->invoice->payment_terms}",
            'Contact accounts: '.route('whatsapp.redirect', ['text' => 'Hi CuciNow, I am contacting you about invoice '.$this->invoice->invoice_number]),
        ]));
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("invoice-{$this->invoice->id}-{$this->invoice->updated_at->timestamp}");
    }
}
