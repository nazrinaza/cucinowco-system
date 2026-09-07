<?php

namespace App\Mail;

use App\Models\NewsletterCampaign;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

class NewsletterPreviewMail extends CuciNowMailable
{
    public function __construct(
        public NewsletterCampaign $campaign,
        public string $previewRecipient,
    ) {}

    public function envelope(): Envelope
    {
        return $this->brandedEnvelope('[TEST] '.$this->campaign->subject, [
            'category' => 'newsletter_preview',
        ]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.newsletter');
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders('newsletter-preview-'.Str::uuid());
    }
}
