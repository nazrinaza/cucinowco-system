<?php

use App\Jobs\SendNewsletterCampaign;
use App\Mail\InvoiceOverdueReminderMail;
use App\Models\Invoice;
use App\Models\NewsletterCampaign;
use App\Support\DocumentEmailHistory;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    NewsletterCampaign::query()
        ->where('status', 'scheduled')
        ->where('scheduled_at', '<=', now())
        ->orderBy('id')
        ->each(function (NewsletterCampaign $campaign): void {
            $claimed = NewsletterCampaign::whereKey($campaign->id)
                ->where('status', 'scheduled')
                ->update(['status' => 'queued', 'delivery_error' => null]);

            if ($claimed) {
                $campaign->refresh();
                SendNewsletterCampaign::dispatch(
                    $campaign->id,
                    (int) $campaign->delivery_cursor,
                    (int) $campaign->delivery_batch_number + 1,
                );
            }
        });
})->everyMinute()->name('queue-due-newsletters')->withoutOverlapping(2);

Schedule::call(function () {
    $leaseSeconds = max(120, (int) config('newsletter.batch_lease_seconds', 300));

    NewsletterCampaign::query()
        ->where('status', 'sending')
        ->whereNotNull('next_batch_at')
        ->where('next_batch_at', '<=', now())
        ->orderBy('id')
        ->each(function (NewsletterCampaign $campaign) use ($leaseSeconds): void {
            $cursor = (int) $campaign->delivery_cursor;
            $batchNumber = (int) $campaign->delivery_batch_number + 1;
            $claimed = NewsletterCampaign::query()
                ->whereKey($campaign->id)
                ->where('status', 'sending')
                ->where('delivery_cursor', $cursor)
                ->where('delivery_batch_number', $batchNumber - 1)
                ->where('next_batch_at', '<=', now())
                ->update(['next_batch_at' => now()->addSeconds($leaseSeconds)]);

            if ($claimed) {
                SendNewsletterCampaign::dispatch($campaign->id, $cursor, $batchNumber);
            }
        });
})->everyMinute()->name('queue-next-newsletter-batches')->withoutOverlapping(2);

Schedule::command('queue:work database --queue=default,newsletters --stop-when-empty --max-time=50 --max-jobs=25 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::call(function () {
    Invoice::query()
        ->whereIn('status', ['sent', 'partial'])
        ->whereDate('due_at', '<', today())
        ->update(['status' => 'overdue']);

    Invoice::query()
        ->with('customer')
        ->where('status', 'overdue')
        ->where('balance', '>', 0)
        ->whereNotNull('due_at')
        ->whereHas('customer', fn ($query) => $query->whereNotNull('email'))
        ->where(fn ($query) => $query
            ->whereNull('last_reminder_sent_at')
            ->orWhere('last_reminder_sent_at', '<=', now()->subDays(7)))
        ->orderBy('id')
        ->each(function (Invoice $invoice): void {
            try {
                app(DocumentEmailHistory::class)->queue($invoice, new InvoiceOverdueReminderMail($invoice), 'invoice_reminder');
                $invoice->update(['last_reminder_sent_at' => now()]);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
})->dailyAt('08:00')->name('mark-overdue-invoices')->withoutOverlapping();
