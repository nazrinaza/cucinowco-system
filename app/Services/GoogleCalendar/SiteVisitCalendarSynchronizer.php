<?php

namespace App\Services\GoogleCalendar;

use App\Models\GoogleCalendarConnection;
use App\Models\SiteVisitCalendarSync;
use App\Models\SiteVisitRequest;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SiteVisitCalendarSynchronizer
{
    private const ACTIVE_STATUSES = ['scheduled', 'completed'];

    public function __construct(private GoogleCalendarClient $client) {}

    public function sync(int $siteVisitId): void
    {
        Cache::lock("google-calendar-site-visit-{$siteVisitId}", 120)->block(10, function () use ($siteVisitId): void {
            $siteVisit = SiteVisitRequest::with(['customer', 'service', 'staff'])->find($siteVisitId);
            if (! $siteVisit) {
                return;
            }

            $sync = SiteVisitCalendarSync::firstOrCreate(
                ['site_visit_request_id' => $siteVisit->id],
                ['status' => 'pending'],
            );

            if (! $this->requiresEvent($siteVisit)) {
                $this->removeEventIfPresent($sync);

                return;
            }

            $connection = GoogleCalendarConnection::active()->latest('id')->first();
            if (! $connection) {
                $sync->update([
                    'status' => 'not_connected',
                    'last_error' => 'Connect the company Google Calendar before syncing.',
                    'last_attempted_at' => now(),
                ]);

                return;
            }

            $payload = $this->eventPayload($siteVisit);
            $fingerprint = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            if ($sync->status === 'synced' && hash_equals((string) $sync->last_synced_fingerprint, $fingerprint)) {
                return;
            }

            $eventId = $sync->google_event_id ?: $this->deterministicEventId($siteVisit);
            $isNew = ! $sync->google_event_id || ! $sync->synced_at;
            $sync->update([
                'google_event_id' => $eventId,
                'status' => 'syncing',
                'attempt_count' => $sync->attempt_count + 1,
                'last_attempted_at' => now(),
                'last_error' => null,
            ]);

            try {
                $isNew
                    ? $this->client->createEvent($connection, $eventId, $payload)
                    : $this->client->updateEvent($connection, $eventId, $payload);
                $sync->update([
                    'status' => 'synced',
                    'last_synced_fingerprint' => $fingerprint,
                    'last_error' => null,
                    'synced_at' => now(),
                ]);
            } catch (Throwable $exception) {
                $this->markFailed($sync, $exception);
                throw $exception;
            }
        });
    }

    private function removeEventIfPresent(SiteVisitCalendarSync $sync): void
    {
        if (! $sync->google_event_id) {
            $sync->update(['status' => 'not_required', 'last_error' => null, 'last_attempted_at' => now()]);

            return;
        }
        if ($sync->status === 'deleted') {
            return;
        }

        $connection = GoogleCalendarConnection::active()->latest('id')->first();
        if (! $connection) {
            $sync->update([
                'status' => 'not_connected',
                'last_error' => 'Reconnect Google Calendar to remove the existing event.',
                'last_attempted_at' => now(),
            ]);

            return;
        }

        try {
            $sync->update([
                'status' => 'syncing',
                'attempt_count' => $sync->attempt_count + 1,
                'last_attempted_at' => now(),
                'last_error' => null,
            ]);
            $this->client->deleteEvent($connection, $sync->google_event_id);
            $sync->update([
                'status' => 'deleted',
                'last_synced_fingerprint' => null,
                'last_error' => null,
                'synced_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->markFailed($sync, $exception);
            throw $exception;
        }
    }

    private function requiresEvent(SiteVisitRequest $siteVisit): bool
    {
        return in_array($siteVisit->status, self::ACTIVE_STATUSES, true)
            && $siteVisit->scheduled_start !== null
            && $siteVisit->scheduled_end !== null;
    }

    private function eventPayload(SiteVisitRequest $siteVisit): array
    {
        $timezone = 'Asia/Kuala_Lumpur';
        $service = $siteVisit->clean_types_label;
        $team = $siteVisit->staff?->name ?: 'Unassigned';

        return [
            'summary' => "SITE VISIT {$siteVisit->reference_number} — {$siteVisit->space_label}",
            'description' => implode("\n", [
                "Reference: {$siteVisit->reference_number}",
                "Customer: {$siteVisit->customer->name}",
                "Assessment scope: {$service}",
                "Assigned team: {$team}",
                'Managed in CuciNow. Changes in Google Calendar do not update the portal.',
            ]),
            'location' => $siteVisit->site_address,
            'start' => [
                'dateTime' => $siteVisit->scheduled_start->copy()->timezone($timezone)->toRfc3339String(),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $siteVisit->scheduled_end->copy()->timezone($timezone)->toRfc3339String(),
                'timeZone' => $timezone,
            ],
            'extendedProperties' => [
                'private' => [
                    'cucinow_site_visit_id' => (string) $siteVisit->id,
                    'cucinow_reference' => $siteVisit->reference_number,
                ],
            ],
        ];
    }

    private function deterministicEventId(SiteVisitRequest $siteVisit): string
    {
        return substr(hash('sha256', "cucinow:site-visit:{$siteVisit->id}:{$siteVisit->reference_number}"), 0, 48);
    }

    private function markFailed(SiteVisitCalendarSync $sync, Throwable $exception): void
    {
        $sync->update([
            'status' => 'failed',
            'last_error' => (string) str($exception->getMessage())->limit(1000),
            'last_attempted_at' => now(),
        ]);
    }
}
