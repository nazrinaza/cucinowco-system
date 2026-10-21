<?php

namespace App\Mail;

use App\Models\NewsletterCampaign;
use App\Support\EmailPlainText;
use App\Support\NewsletterHtmlSanitizer;
use App\Support\NewsletterMergeTags;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

class NewsletterPreviewMail extends CuciNowMailable
{
    public string $subjectLine;

    public string $previewLine;

    public string $htmlContent;

    public string $plainTextContent;

    public function __construct(
        public NewsletterCampaign $campaign,
        public string $previewRecipient,
        public ?string $previewName = null,
    ) {
        $mergeTags = new NewsletterMergeTags(
            $this->previewName,
            $this->previewRecipient,
            rtrim((string) config('app.url'), '/'),
        );
        $this->subjectLine = $mergeTags->renderText($campaign->subject);
        $this->previewLine = $mergeTags->renderText($campaign->preview_text ?: $campaign->subject);
        $this->htmlContent = $mergeTags->renderHtml(
            app(NewsletterHtmlSanitizer::class)->sanitize($campaign->content),
        );
        $this->plainTextContent = EmailPlainText::fromHtml($this->htmlContent);
    }

    public function envelope(): Envelope
    {
        return $this->brandedEnvelope('[TEST] '.$this->subjectLine, [
            'category' => 'newsletter_preview',
        ]);
    }

    public function content(): Content
    {
        return $this->brandedContent('emails.newsletter', implode("\n", [
            '[TEST] '.$this->subjectLine,
            '',
            $this->plainTextContent,
            '',
            'Book a free site visit: '.url('/#site-visit'),
            "This is a private newsletter preview sent to {$this->previewRecipient}.",
        ]));
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders('newsletter-preview-'.Str::uuid());
    }
}
