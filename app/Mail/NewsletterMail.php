<?php

namespace App\Mail;

use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

class NewsletterMail extends CuciNowMailable
{
    public string $unsubscribeUrl;

    public function __construct(
        public NewsletterCampaign $campaign,
        public Subscriber $subscriber,
    ) {
        $this->unsubscribeUrl = URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $subscriber->id]);
    }

    public function envelope(): Envelope
    {
        return $this->brandedEnvelope($this->campaign->subject, [
            'category' => 'newsletter',
            'campaign_id' => $this->campaign->id,
            'subscriber_id' => $this->subscriber->id,
        ]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.newsletter');
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders(
            "campaign-{$this->campaign->id}-subscriber-{$this->subscriber->id}",
            [
                'List-Unsubscribe' => "<{$this->unsubscribeUrl}>",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }
}
