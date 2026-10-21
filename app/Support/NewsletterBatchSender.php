<?php

namespace App\Support;

use App\Mail\NewsletterMail;
use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use Illuminate\Support\Collection;
use Resend\Contracts\Client;

class NewsletterBatchSender
{
    public function __construct(private Client $resend) {}

    /**
     * Submit one batch to Resend. Every recipient remains an individually
     * addressed email so merge tags, unsubscribe links and webhook analytics
     * continue to work as before.
     *
     * @param  Collection<int, Subscriber>  $subscribers
     */
    public function send(NewsletterCampaign $campaign, Collection $subscribers, int $batchNumber): void
    {
        $messages = $subscribers
            ->map(fn (Subscriber $subscriber): array => $this->message($campaign, $subscriber))
            ->values()
            ->all();

        if ($messages === []) {
            return;
        }

        $this->resend->batch()->send($messages, [
            'idempotency_key' => "newsletter-campaign-{$campaign->id}-batch-{$batchNumber}",
            'batch_validation' => 'strict',
        ]);
    }

    private function message(NewsletterCampaign $campaign, Subscriber $subscriber): array
    {
        $mail = new NewsletterMail($campaign, $subscriber);

        return [
            'from' => $this->fromAddress(),
            'to' => [$subscriber->email],
            'reply_to' => [config('company.email')],
            'subject' => $mail->subjectLine,
            'html' => $mail->render(),
            'text' => view('emails.plain', [
                'plainText' => implode("\n", [
                    $mail->subjectLine,
                    '',
                    $mail->plainTextContent,
                    '',
                    'Book a free site visit: '.url('/#site-visit'),
                    "Unsubscribe: {$mail->unsubscribeUrl}",
                ]),
            ])->render(),
            'headers' => [
                'List-Unsubscribe' => "<{$mail->unsubscribeUrl}>",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
            'tags' => [
                ['name' => 'category', 'value' => 'newsletter'],
                ['name' => 'campaign_id', 'value' => (string) $campaign->id],
                ['name' => 'subscriber_id', 'value' => (string) $subscriber->id],
            ],
        ];
    }

    private function fromAddress(): string
    {
        $email = trim((string) config('mail.from.address'));
        $name = trim(str_replace(["\r", "\n", '<', '>'], '', (string) config('mail.from.name')));

        return $name === '' ? $email : "{$name} <{$email}>";
    }
}
