<?php

namespace App\Jobs;

use App\Mail\NewsletterMail;
use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNewsletterCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 50;

    public function __construct(public int $campaignId) {}

    public function handle(): void
    {
        $campaign = NewsletterCampaign::findOrFail($this->campaignId);

        if (! in_array($campaign->status, ['queued', 'sending'], true)) {
            return;
        }

        $recipientCount = Subscriber::where('status', 'subscribed')->count();
        $campaign->update([
            'status' => 'sending',
            'recipient_count' => $recipientCount,
            'delivery_error' => null,
        ]);

        Subscriber::query()
            ->where('status', 'subscribed')
            ->orderBy('id')
            ->chunkById(100, function ($subscribers) use ($campaign): void {
                foreach ($subscribers as $subscriber) {
                    Mail::to($subscriber->email, $subscriber->name)
                        ->queue(new NewsletterMail($campaign, $subscriber));
                }
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
