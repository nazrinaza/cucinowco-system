<?php

namespace App\Jobs;

use App\Models\BookingCalendarSync;
use App\Services\GoogleCalendar\BookingCalendarSynchronizer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncBookingToGoogleCalendar implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 45;

    public int $uniqueFor = 300;

    public function __construct(public int $bookingId)
    {
        $this->onQueue((string) config('services.google.calendar.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return "booking-calendar-{$this->bookingId}";
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(BookingCalendarSynchronizer $synchronizer): void
    {
        $synchronizer->sync($this->bookingId);
    }

    public function failed(?Throwable $exception): void
    {
        BookingCalendarSync::where('booking_id', $this->bookingId)->update([
            'status' => 'failed',
            'last_error' => (string) str($exception?->getMessage() ?: 'Calendar sync failed.')->limit(1000),
            'last_attempted_at' => now(),
        ]);
    }
}
