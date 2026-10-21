<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class QuoteMail extends CuciNowMailable
{
    public function __construct(public Quote $quote) {}

    public function envelope(): Envelope
    {
        return $this->brandedEnvelope(
            "CuciNow quotation {$this->quote->quote_number}",
            ['category' => 'quotation', 'quote_id' => $this->quote->id],
        );
    }

    public function content(): Content
    {
        $items = $this->quote->items
            ->map(fn ($item): string => "- {$item->description}: RM ".number_format($item->amount, 2))
            ->implode("\n");

        return $this->brandedContent('emails.quote', implode("\n", [
            "Quotation {$this->quote->quote_number}",
            "Prepared for {$this->quote->customer->name}",
            'Valid until: '.$this->quote->valid_until?->format('d M Y'),
            '',
            $items,
            '',
            'Total: RM '.number_format($this->quote->total, 2),
            "Service location: {$this->quote->service_address}, {$this->quote->postcode} {$this->quote->city}, {$this->quote->state}",
            'Discuss this quotation: '.route('whatsapp.redirect', ['text' => 'Hi CuciNow, I would like to discuss quotation '.$this->quote->quote_number]),
        ]));
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("quote-{$this->quote->id}-{$this->quote->updated_at->timestamp}");
    }
}
