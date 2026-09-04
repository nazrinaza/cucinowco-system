<?php

namespace App\Mail;

use App\Models\Subscriber;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

class SubscriberWelcomeMail extends CuciNowMailable
{
    public string $unsubscribeUrl;

    public function __construct(public Subscriber $subscriber)
    {
        $this->unsubscribeUrl = URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $subscriber->id]);
    }

    public function envelope(): Envelope
    {
        return $this->brandedEnvelope('Welcome to CuciNow updates', [
            'category' => 'newsletter_welcome',
            'subscriber_id' => $this->subscriber->id,
        ]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.subscriber-welcome');
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders(
            "subscriber-welcome-{$this->subscriber->id}-{$this->subscriber->subscribed_at->timestamp}",
            [
                'List-Unsubscribe' => "<{$this->unsubscribeUrl}>",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }
}
