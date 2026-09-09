<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_assignment_matches_integer_ids_when_database_returns_strings(): void
    {
        $booking = new Booking;
        $booking->setRawAttributes(['staff_id' => '3']);

        $this->assertSame(3, $booking->staff_id);

        $booking->setRawAttributes(['staff_id' => null]);

        $this->assertNull($booking->staff_id);
    }

    public function test_admin_month_calendar_marks_confirmed_and_completed_bookings(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $service = Service::create([
            'code' => 'office-cleaning',
            'name' => 'Office Cleaning',
            'is_active' => true,
        ]);
        $confirmedCustomer = Customer::create([
            'name' => 'Confirmed Client',
            'phone' => '0111111111',
        ]);
        $completedCustomer = Customer::create([
            'name' => 'Completed Client',
            'phone' => '0122222222',
        ]);
        Booking::create([
            'booking_number' => 'BK-CALENDAR-1',
            'customer_id' => $confirmedCustomer->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
            'scheduled_start' => '2026-09-08 09:00:00',
            'service_address' => 'Shah Alam',
        ]);
        Booking::create([
            'booking_number' => 'BK-CALENDAR-2',
            'customer_id' => $completedCustomer->id,
            'service_id' => $service->id,
            'status' => 'completed',
            'scheduled_start' => '2026-09-15 14:00:00',
            'service_address' => 'Petaling Jaya',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.bookings.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertSee('September 2026')
            ->assertSee('Confirmed Client')
            ->assertSee('Completed Client')
            ->assertSee('calendar-booking-confirmed', false)
            ->assertSee('calendar-booking-completed', false)
            ->assertSee('booking-date-completed', false)
            ->assertViewHas('calendarDays', fn ($days): bool => $days->count() === 35)
            ->assertViewHas('calendarCounts', fn ($counts): bool => $counts['confirmed'] === 1
                && $counts['completed'] === 1
                && $counts->sum() === 2);
    }

    public function test_calendar_month_and_status_filters_are_validated(): void
    {
        $admin = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.bookings.index', ['month' => 'not-a-month', 'status' => 'unknown']))
            ->assertSessionHasErrors(['month', 'status']);
    }
}
