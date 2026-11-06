<?php

namespace App\Jobs;

use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use App\Support\NewsletterBatchSender;
use App\Support\SubscriberSegments;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendNewsletterCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $campaignId,
        public int $expectedCursor = 0,
        public int $expectedBatchNumber = 1,
    ) {
        $this->onQueue((string) config('newsletter.queue', 'newsletters'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("newsletter-campaign-{$this->campaignId}"))
                ->dontRelease()
                ->expireAfter(max(120, (int) config('newsletter.batch_lease_seconds', 300))),
        ];
    }

    public function handle(
        ?SubscriberSegments $subscriberSegments = null,
        ?NewsletterBatchSender $batchSender = null,
    ): void {
        $subscriberSegments ??= app(SubscriberSegments::class);
        $batchSender ??= app(NewsletterBatchSender::class);
        $campaign = $this->prepareCampaign($subscriberSegments);

        if (! $campaign || $campaign->status !== 'sending') {
            return;
        }

        if ((int) $campaign->delivery_cursor !== $this->expectedCursor
            || (int) $campaign->delivery_batch_number + 1 !== $this->expectedBatchNumber) {
            return;
        }

        $leaseSeconds = max(120, (int) config('newsletter.batch_lease_seconds', 300));
        $claimed = NewsletterCampaign::query()
            ->whereKey($campaign->id)
            ->where('status', 'sending')
            ->where('delivery_cursor', $this->expectedCursor)
            ->where('delivery_batch_number', $this->expectedBatchNumber - 1)
            ->update(['next_batch_at' => now()->addSeconds($leaseSeconds)]);

        if ($claimed === 0) {
            return;
        }

        $batchSize = max(1, min(100, (int) config('newsletter.batch_size', 100)));
        $subscribers = $this->recipientQuery($campaign, $subscriberSegments)
            ->where('id', '>', $this->expectedCursor)
            ->where('id', '<=', (int) $campaign->delivery_max_subscriber_id)
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        if ($subscribers->isEmpty()) {
            $this->completeCampaign();

            return;
        }

        $batchSender->send($campaign, $subscribers, $this->expectedBatchNumber);

        $lastSubscriberId = (int) $subscribers->last()->id;
        $hasMore = $this->recipientQuery($campaign, $subscriberSegments)
            ->where('id', '>', $lastSubscriberId)
            ->where('id', '<=', (int) $campaign->delivery_max_subscriber_id)
            ->exists();

        NewsletterCampaign::query()
            ->whereKey($campaign->id)
            ->where('status', 'sending')
            ->where('delivery_cursor', $this->expectedCursor)
            ->where('delivery_batch_number', $this->expectedBatchNumber - 1)
            ->update([
                'delivery_cursor' => $lastSubscriberId,
                'delivery_batch_number' => $this->expectedBatchNumber,
                'processed_count' => DB::raw('processed_count + '.(int) $subscribers->count()),
                'status' => $hasMore ? 'sending' : 'sent',
                'sent_at' => $hasMore ? null : now(),
                'next_batch_at' => $hasMore
                    ? now()->addSeconds(max(30, (int) config('newsletter.batch_delay_seconds', 60)))
                    : null,
            ]);
    }

    public function failed(Throwable $exception): void
    {
        NewsletterCampaign::query()
            ->whereKey($this->campaignId)
            ->where('delivery_cursor', $this->expectedCursor)
            ->where('delivery_batch_number', $this->expectedBatchNumber - 1)
            ->update([
                'status' => 'failed',
                'next_batch_at' => null,
                'delivery_error' => (string) str($exception->getMessage())->limit(2000),
            ]);
    }

    private function prepareCampaign(SubscriberSegments $subscriberSegments): ?NewsletterCampaign
    {
        return DB::transaction(function () use ($subscriberSegments): ?NewsletterCampaign {
            $campaign = NewsletterCampaign::query()->lockForUpdate()->find($this->campaignId);

            if (! $campaign || ! in_array($campaign->status, ['queued', 'sending'], true)) {
                return null;
            }

            if ($campaign->delivery_started_at === null) {
                $recipientQuery = $this->recipientQuery($campaign, $subscriberSegments);
                $campaign->forceFill([
                    'status' => 'sending',
                    'recipient_count' => (clone $recipientQuery)->count(),
                    'delivery_cursor' => 0,
                    'delivery_max_subscriber_id' => (int) ((clone $recipientQuery)->max('id') ?? 0),
                    'delivery_batch_number' => 0,
                    'processed_count' => 0,
                    'delivery_started_at' => now(),
                    'next_batch_at' => now()->addSeconds(max(120, (int) config('newsletter.batch_lease_seconds', 300))),
                    'delivery_error' => null,
                ])->save();
            } elseif ($campaign->status === 'queued') {
                $campaign->forceFill([
                    'status' => 'sending',
                    'next_batch_at' => now()->addSeconds(max(120, (int) config('newsletter.batch_lease_seconds', 300))),
                    'delivery_error' => null,
                ])->save();
            }

            return $campaign->fresh();
        });
    }

    private function recipientQuery(NewsletterCampaign $campaign, SubscriberSegments $subscriberSegments): Builder
    {
        return $subscriberSegments->constrain(
            Subscriber::query()->where('status', 'subscribed'),
            $subscriberSegments->normalize($campaign->segments),
        );
    }

    private function completeCampaign(): void
    {
        NewsletterCampaign::query()
            ->whereKey($this->campaignId)
            ->where('status', 'sending')
            ->where('delivery_cursor', $this->expectedCursor)
            ->where('delivery_batch_number', $this->expectedBatchNumber - 1)
            ->update([
                'status' => 'sent',
                'sent_at' => now(),
                'next_batch_at' => null,
            ]);
    }
}
