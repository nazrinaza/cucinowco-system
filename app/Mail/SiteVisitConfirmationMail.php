<?php

namespace App\Mail;

use App\Models\SiteVisitRequest;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class SiteVisitConfirmationMail extends CuciNowMailable
{
    public function __construct(public SiteVisitRequest $siteVisit) {}

    public function envelope(): Envelope
    {
        return $this->brandedEnvelope(
            "Your free site visit request {$this->siteVisit->reference_number}",
            ['category' => 'site_visit_confirmation', 'site_visit_id' => $this->siteVisit->id],
        );
    }

    public function content(): Content
    {
        return $this->brandedContent('emails.site-visit-confirmation', implode("\n", [
            "Thank you, {$this->siteVisit->customer->name}.",
            'We have received your complimentary site visit request. Our team will contact you to confirm the appointment.',
            "Reference: {$this->siteVisit->reference_number}",
            "Space type: {$this->siteVisit->space_label}",
            "Clean type: {$this->siteVisit->clean_types_label}",
            'Preferred date: '.$this->siteVisit->preferred_date?->format('d M Y'),
            'Continue on WhatsApp: '.route('whatsapp.redirect', ['text' => 'Hi CuciNow, my free site visit reference is '.$this->siteVisit->reference_number]),
        ]));
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("site-visit-customer-{$this->siteVisit->id}");
    }
}
