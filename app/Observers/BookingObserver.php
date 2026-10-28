<?php

namespace App\Observers;

use App\Jobs\SyncBookingToGoogleCalendar;
use App\Models\Booking;
use App\Models\BookingCalendarSync;

class BookingObserver
{
    private const SYNC_FIELDS = [
        'booking_number', 'customer_id', 'service_id', 'staff_id', 'status',
        'scheduled_start', 'scheduled_end', 'service_address',
    ];

    public function created(Booking $booking): void
    {
        if ($booking->status === 'confirmed') {
            $this->queue($booking);
        }
    }

    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged(self::SYNC_FIELDS)) {
            $this->queue($booking);
        }
    }

    private function queue(Booking $booking): void
    {
        BookingCalendarSync::updateOrCreate(
            ['booking_id' => $booking->id],
            ['status' => 'queued', 'last_error' => null],
        );
        SyncBookingToGoogleCalendar::dispatch($booking->id)->afterCommit();
    }
}
