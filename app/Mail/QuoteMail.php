<?php

namespace App\Mail;

use App\Models\Quote;
use App\Support\QuotationHtmlSanitizer;
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
        $sanitizer = app(QuotationHtmlSanitizer::class);
        $items = $this->quote->items
            ->map(function ($item) use ($sanitizer): string {
                $notes = $sanitizer->toPlainText($item->notes);

                return "- {$item->description}: RM ".number_format($item->amount, 2)
                    .($notes !== '' ? "\n  ".str_replace("\n", "\n  ", $notes) : '');
            })
            ->implode("\n");
        $terms = collect(config('company.quotation_terms'))
            ->map(fn (string $term, int $index): string => ($index + 1).'. '.$term)
            ->implode("\n");
        $contacts = collect($this->quote->contact_people ?? [])
            ->map(function (array $contact): string {
                $identity = $contact['name'].(filled($contact['role'] ?? null) ? " ({$contact['role']})" : '');
                $details = collect([$contact['phone'] ?? null, $contact['email'] ?? null])->filter()->implode(' · ');

                return "- {$identity}: {$details}";
            })
            ->values();
        $contactSection = $contacts->isEmpty() ? [] : ['', 'Project contacts', ...$contacts->all()];

        return $this->brandedContent('emails.quote', implode("\n", [
            "Quotation {$this->quote->quote_number}",
            "Prepared for {$this->quote->customer->name}",
            'Valid until: '.$this->quote->valid_until?->format('d M Y'),
            ...$contactSection,
            '',
            $items,
            '',
            'Total: RM '.number_format($this->quote->total, 2),
            "Service location: {$this->quote->service_address}, {$this->quote->postcode} {$this->quote->city}, {$this->quote->state}",
            '',
            'Project & payment terms',
            $terms,
            '',
            'Discuss this quotation: '.route('whatsapp.redirect', ['text' => 'Hi CuciNow, I would like to discuss quotation '.$this->quote->quote_number]),
        ]));
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("quote-{$this->quote->id}-{$this->quote->updated_at->timestamp}");
    }
}
