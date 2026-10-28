<?php

namespace App\Jobs;

use App\Models\SiteVisitCalendarSync;
use App\Services\GoogleCalendar\SiteVisitCalendarSynchronizer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncSiteVisitToGoogleCalendar implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 45;

    public int $uniqueFor = 300;

    public function __construct(public int $siteVisitId)
    {
        $this->onQueue((string) config('services.google.calendar.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return "site-visit-calendar-{$this->siteVisitId}";
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(SiteVisitCalendarSynchronizer $synchronizer): void
    {
        $synchronizer->sync($this->siteVisitId);
    }

    public function failed(?Throwable $exception): void
    {
        SiteVisitCalendarSync::where('site_visit_request_id', $this->siteVisitId)->update([
            'status' => 'failed',
            'last_error' => (string) str($exception?->getMessage() ?: 'Calendar sync failed.')->limit(1000),
            'last_attempted_at' => now(),
        ]);
    }
}
