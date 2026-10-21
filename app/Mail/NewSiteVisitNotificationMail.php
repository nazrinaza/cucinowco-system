<?php

namespace App\Mail;

use App\Models\SiteVisitRequest;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class NewSiteVisitNotificationMail extends CuciNowMailable
{
    public function __construct(public SiteVisitRequest $siteVisit) {}

    public function envelope(): Envelope
    {
        return $this->brandedEnvelope(
            "New site visit request: {$this->siteVisit->reference_number}",
            ['category' => 'site_visit_admin', 'site_visit_id' => $this->siteVisit->id],
        );
    }

    public function content(): Content
    {
        return $this->brandedContent('emails.new-site-visit-notification', implode("\n", [
            "New site visit request: {$this->siteVisit->reference_number}",
            "Customer: {$this->siteVisit->customer->name}",
            "Space type: {$this->siteVisit->space_label}",
            "Clean type: {$this->siteVisit->clean_types_label}",
            'Preferred: '.$this->siteVisit->preferred_date?->format('d M Y').' - '.str($this->siteVisit->preferred_time_slot)->replace('_', ' ')->title(),
            "Contact: {$this->siteVisit->customer->phone} / {$this->siteVisit->customer->email}",
            'Review request: '.route('admin.site-visits.show', $this->siteVisit),
        ]));
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("site-visit-admin-{$this->siteVisit->id}");
    }
}
