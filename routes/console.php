<?php

use App\Jobs\SendNewsletterCampaign;
use App\Mail\InvoiceOverdueReminderMail;
use App\Models\Invoice;
use App\Models\NewsletterCampaign;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
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
                SendNewsletterCampaign::dispatch($campaign->id);
            }
        });
})->everyMinute()->name('queue-due-newsletters')->withoutOverlapping();

Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

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
            $invoice->update(['last_reminder_sent_at' => now()]);
            Mail::to($invoice->customer->email, $invoice->customer->name)
                ->queue(new InvoiceOverdueReminderMail($invoice));
        });
})->dailyAt('08:00')->name('mark-overdue-invoices')->withoutOverlapping();
