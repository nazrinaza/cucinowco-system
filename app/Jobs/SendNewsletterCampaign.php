<?php

namespace App\Jobs;

use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use App\Support\NewsletterBatchSender;
use App\Support\SubscriberSegments;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendNewsletterCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 50;

    public function __construct(public int $campaignId)
    {
        $this->onQueue((string) config('newsletter.queue', 'newsletters'));
    }

    public function handle(
        ?SubscriberSegments $subscriberSegments = null,
        ?NewsletterBatchSender $batchSender = null,
    ): void {
        $subscriberSegments ??= app(SubscriberSegments::class);
        $batchSender ??= app(NewsletterBatchSender::class);
        $campaign = NewsletterCampaign::findOrFail($this->campaignId);

        if (! in_array($campaign->status, ['queued', 'sending'], true)) {
            return;
        }

        $segments = $subscriberSegments->normalize($campaign->segments);
        $recipientQuery = $subscriberSegments->constrain(
            Subscriber::query()->where('status', 'subscribed'),
            $segments,
        );
        $recipientCount = (clone $recipientQuery)->count();
        $campaign->update([
            'status' => 'sending',
            'recipient_count' => $recipientCount,
            'delivery_error' => null,
        ]);

        $batchSize = max(1, min(100, (int) config('newsletter.batch_size', 100)));
        $batchNumber = 0;

        $recipientQuery
            ->orderBy('id')
            ->chunkById($batchSize, function ($subscribers) use ($campaign, $batchSender, &$batchNumber): void {
                $batchSender->send($campaign, $subscribers, ++$batchNumber);
            });

        $campaign->update(['status' => 'sent', 'sent_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        NewsletterCampaign::whereKey($this->campaignId)->update([
            'status' => 'failed',
            'delivery_error' => (string) str($exception->getMessage())->limit(2000),
        ]);
    }
}
