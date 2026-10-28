<?php

namespace App\Observers;

use App\Jobs\SyncSiteVisitToGoogleCalendar;
use App\Models\SiteVisitCalendarSync;
use App\Models\SiteVisitRequest;

class SiteVisitRequestObserver
{
    private const SYNC_FIELDS = [
        'reference_number', 'customer_id', 'service_id', 'staff_id', 'status',
        'space_type', 'clean_types', 'other_clean_type', 'scheduled_start',
        'scheduled_end', 'site_address',
    ];

    public function created(SiteVisitRequest $siteVisit): void
    {
        if ($siteVisit->status === 'scheduled') {
            $this->queue($siteVisit);
        }
    }

    public function updated(SiteVisitRequest $siteVisit): void
    {
        if ($siteVisit->wasChanged(self::SYNC_FIELDS)) {
            $this->queue($siteVisit);
        }
    }

    private function queue(SiteVisitRequest $siteVisit): void
    {
        SiteVisitCalendarSync::updateOrCreate(
            ['site_visit_request_id' => $siteVisit->id],
            ['status' => 'queued', 'last_error' => null],
        );
        SyncSiteVisitToGoogleCalendar::dispatch($siteVisit->id)->afterCommit();
    }
}
