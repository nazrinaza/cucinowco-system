<?php

namespace App\Mail;

use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use App\Support\EmailPlainText;
use App\Support\NewsletterHtmlSanitizer;
use App\Support\NewsletterMergeTags;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

class NewsletterMail extends CuciNowMailable
{
    public string $unsubscribeUrl;

    public string $subjectLine;

    public string $previewLine;

    public string $htmlContent;

    public string $plainTextContent;

    public function __construct(
        public NewsletterCampaign $campaign,
        public Subscriber $subscriber,
    ) {
        $this->unsubscribeUrl = URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $subscriber->id]);
        $mergeTags = new NewsletterMergeTags($subscriber->name, $subscriber->email, $this->unsubscribeUrl);
        $this->subjectLine = $mergeTags->renderText($campaign->subject);
        $this->previewLine = $mergeTags->renderText($campaign->preview_text ?: $campaign->subject);
        $this->htmlContent = $mergeTags->renderHtml(
            app(NewsletterHtmlSanitizer::class)->sanitize($campaign->content),
        );
        $this->plainTextContent = EmailPlainText::fromHtml($this->htmlContent);
    }

    public function envelope(): Envelope
    {
        return $this->brandedEnvelope($this->subjectLine, [
            'category' => 'newsletter',
            'campaign_id' => $this->campaign->id,
            'subscriber_id' => $this->subscriber->id,
        ]);
    }

    public function content(): Content
    {
        return $this->brandedContent('emails.newsletter', implode("\n", [
            $this->subjectLine,
            '',
            $this->plainTextContent,
            '',
            'Book a free site visit: '.route('home', ['ref' => 'newsletter']).'#site-visit',
            "Unsubscribe: {$this->unsubscribeUrl}",
        ]));
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
