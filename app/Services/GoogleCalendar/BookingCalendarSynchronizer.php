<?php

namespace App\Services\GoogleCalendar;

use App\Models\Booking;
use App\Models\BookingCalendarSync;
use App\Models\GoogleCalendarConnection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class BookingCalendarSynchronizer
{
    private const ACTIVE_STATUSES = ['confirmed', 'in_progress', 'completed'];

    public function __construct(private GoogleCalendarClient $client) {}

    public function sync(int $bookingId): void
    {
        Cache::lock("google-calendar-booking-{$bookingId}", 120)->block(10, function () use ($bookingId): void {
            $booking = Booking::with(['customer', 'service', 'staff'])->find($bookingId);
            if (! $booking) {
                return;
            }

            $sync = BookingCalendarSync::firstOrCreate(
                ['booking_id' => $booking->id],
                ['status' => 'pending'],
            );

            if (! $this->requiresEvent($booking)) {
                $this->removeEventIfPresent($booking, $sync);

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

            $payload = $this->eventPayload($booking);
            $fingerprint = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            if ($sync->status === 'synced' && hash_equals((string) $sync->last_synced_fingerprint, $fingerprint)) {
                return;
            }

            $eventId = $sync->google_event_id ?: $this->deterministicEventId($booking);
            $isNew = ! $sync->google_event_id || ! $sync->synced_at;
            $sync->update([
                'google_event_id' => $eventId,
                'status' => 'syncing',
                'attempt_count' => $sync->attempt_count + 1,
                'last_attempted_at' => now(),
                'last_error' => null,
            ]);

            try {
                if ($isNew) {
                    $this->client->createEvent($connection, $eventId, $payload);
                } else {
                    $this->client->updateEvent($connection, $eventId, $payload);
                }

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

    private function removeEventIfPresent(Booking $booking, BookingCalendarSync $sync): void
    {
        if (! $sync->google_event_id) {
            $sync->update([
                'status' => 'not_required',
                'last_error' => null,
                'last_attempted_at' => now(),
            ]);

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

    private function requiresEvent(Booking $booking): bool
    {
        return in_array($booking->status, self::ACTIVE_STATUSES, true) && $booking->scheduled_start !== null;
    }

    private function eventPayload(Booking $booking): array
    {
        $timezone = 'Asia/Kuala_Lumpur';
        $start = $booking->scheduled_start->copy()->timezone($timezone);
        $end = ($booking->scheduled_end ?: $booking->scheduled_start->copy()->addMinutes(
            max(15, (int) config('services.google.calendar.default_duration_minutes', 120)),
        ))->copy()->timezone($timezone);
        $service = $booking->service?->name ?: 'Cleaning service';
        $team = $booking->staff?->name ?: 'Unassigned';

        return [
            'summary' => "{$booking->booking_number} — {$service}",
            'description' => implode("\n", [
                "Reference: {$booking->booking_number}",
                "Customer: {$booking->customer->name}",
                "Service: {$service}",
                "Assigned team: {$team}",
                'Managed in CuciNow. Changes in Google Calendar do not update the portal.',
            ]),
            'location' => $booking->service_address,
            'start' => ['dateTime' => $start->toRfc3339String(), 'timeZone' => $timezone],
            'end' => ['dateTime' => $end->toRfc3339String(), 'timeZone' => $timezone],
            'extendedProperties' => [
                'private' => [
                    'cucinow_booking_id' => (string) $booking->id,
                    'cucinow_reference' => $booking->booking_number,
                ],
            ],
        ];
    }

    private function deterministicEventId(Booking $booking): string
    {
        return substr(hash('sha256', "cucinow:booking:{$booking->id}:{$booking->booking_number}"), 0, 48);
    }

    private function markFailed(BookingCalendarSync $sync, Throwable $exception): void
    {
        $sync->update([
            'status' => 'failed',
            'last_error' => (string) str($exception->getMessage())->limit(1000),
            'last_attempted_at' => now(),
        ]);
    }
}
