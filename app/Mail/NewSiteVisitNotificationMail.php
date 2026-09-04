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
        return new Content(view: 'emails.new-site-visit-notification');
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("site-visit-admin-{$this->siteVisit->id}");
    }
}
