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
        return new Content(view: 'emails.quote');
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("quote-{$this->quote->id}-{$this->quote->updated_at->timestamp}");
    }
}
