<?php

namespace Tests\Feature;

use App\Exceptions\GoogleCalendarException;
use App\Jobs\SyncBookingToGoogleCalendar;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\GoogleCalendarConnection;
use App\Models\Service;
use App\Models\SiteVisitRequest;
use App\Models\Staff;
use App\Models\User;
use App\Services\GoogleCalendar\BookingCalendarSynchronizer;
use App\Services\GoogleCalendar\SiteVisitCalendarSynchronizer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GoogleCalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-28 10:00:00');
        config([
            'app.timezone' => 'Asia/Kuala_Lumpur',
            'services.google.calendar.client_id' => 'test-client-id',
            'services.google.calendar.client_secret' => 'test-client-secret',
            'services.google.calendar.calendar_id' => 'company@group.calendar.google.com',
            'services.google.calendar.default_duration_minutes' => 120,
        ]);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_confirmed_booking_creates_one_operational_event_in_kuala_lumpur_timezone(): void
    {
        $booking = $this->booking();
        $this->connection();
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response(['id' => 'created'], 200)]);

        app(BookingCalendarSynchronizer::class)->sync($booking->id);

        $sync = $booking->calendarSync()->firstOrFail();
        $this->assertSame('synced', $sync->status);
        $this->assertNotEmpty($sync->google_event_id);
        Http::assertSent(function (Request $request) use ($booking): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && str_contains($request->url(), '/calendar/v3/calendars/company%40group.calendar.google.com/events')
                && $payload['summary'] === 'BK-GCAL-1 — Office Deep Clean'
                && str_contains($payload['description'], 'Customer: Operations Contact')
                && str_contains($payload['description'], 'Assigned team: Team Alpha')
                && ! str_contains($payload['description'], 'customer@example.com')
                && ! str_contains($payload['description'], '0111111111')
                && $payload['location'] === 'Level 8, Example Tower, Kuala Lumpur'
                && $payload['start']['timeZone'] === 'Asia/Kuala_Lumpur'
                && $payload['end']['timeZone'] === 'Asia/Kuala_Lumpur'
                && str_contains($payload['start']['dateTime'], '+08:00')
                && $payload['id'] === $booking->calendarSync()->value('google_event_id');
        });
    }

    public function test_pending_booking_does_not_create_an_event(): void
    {
        $booking = $this->booking(['status' => 'pending']);
        $this->connection();
        Http::fake();

        app(BookingCalendarSynchronizer::class)->sync($booking->id);

        Http::assertNothingSent();
        $this->assertSame('not_required', $booking->calendarSync()->value('status'));
    }

    public function test_scheduled_site_visit_creates_an_appointment_with_exact_times_and_team(): void
    {
        $customer = Customer::create([
            'name' => 'Facilities Contact',
            'email' => 'facilities@example.com',
            'phone' => '0122222222',
        ]);
        $staff = Staff::create(['name' => 'Assessment Team']);
        $siteVisit = SiteVisitRequest::create([
            'reference_number' => 'SV-GCAL-1',
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'status' => 'scheduled',
            'space_type' => 'hall',
            'clean_types' => ['tiles_cleaning', 'window_cleaning'],
            'preferred_date' => '2026-11-03',
            'preferred_time_slot' => 'morning',
            'scheduled_start' => '2026-11-03 10:00:00',
            'scheduled_end' => '2026-11-03 11:30:00',
            'site_address' => 'Grand Hall, Shah Alam',
        ]);
        $this->connection();
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response([], 200)]);

        app(SiteVisitCalendarSynchronizer::class)->sync($siteVisit->id);

        $this->assertSame('synced', $siteVisit->calendarSync()->value('status'));
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $payload['summary'] === 'SITE VISIT SV-GCAL-1 — Grand Hall'
                && str_contains($payload['description'], 'Assigned team: Assessment Team')
                && str_contains($payload['description'], 'Tiles Cleaning, Window Cleaning')
                && ! str_contains($payload['description'], 'facilities@example.com')
                && $payload['start']['dateTime'] === '2026-11-03T10:00:00+08:00'
                && $payload['end']['dateTime'] === '2026-11-03T11:30:00+08:00';
        });
    }

    public function test_booking_change_updates_the_same_google_event(): void
    {
        $booking = $this->booking();
        $this->connection();
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response([], 200)]);
        $synchronizer = app(BookingCalendarSynchronizer::class);
        $synchronizer->sync($booking->id);
        $eventId = $booking->calendarSync()->value('google_event_id');

        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response([], 200)]);
        $booking->update(['scheduled_start' => '2026-11-02 11:30:00']);
        $synchronizer->sync($booking->id);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/'.$eventId));
        $this->assertSame($eventId, $booking->calendarSync()->value('google_event_id'));
    }

    public function test_cancellation_deletes_the_existing_event_and_retry_is_idempotent(): void
    {
        $booking = $this->booking();
        $this->connection();
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response([], 200)]);
        $synchronizer = app(BookingCalendarSynchronizer::class);
        $synchronizer->sync($booking->id);
        $eventId = $booking->calendarSync()->value('google_event_id');

        $booking->update(['status' => 'cancelled']);
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response([], 204)]);
        $synchronizer->sync($booking->id);
        $synchronizer->sync($booking->id);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/'.$eventId));
        $this->assertSame('deleted', $booking->calendarSync()->value('status'));
    }

    public function test_unchanged_retry_does_not_duplicate_an_event(): void
    {
        $booking = $this->booking();
        $this->connection();
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response([], 200)]);
        $synchronizer = app(BookingCalendarSynchronizer::class);

        $synchronizer->sync($booking->id);
        $synchronizer->sync($booking->id);

        Http::assertSentCount(1);
    }

    public function test_api_failure_is_recorded_and_job_has_retry_backoff(): void
    {
        $booking = $this->booking();
        $this->connection();
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response(['error' => ['message' => 'Temporary outage']], 503)]);

        try {
            app(BookingCalendarSynchronizer::class)->sync($booking->id);
            $this->fail('Expected calendar exception was not thrown.');
        } catch (GoogleCalendarException) {
            $this->assertSame('failed', $booking->calendarSync()->value('status'));
            $this->assertStringContainsString('Temporary outage', $booking->calendarSync()->value('last_error'));
        }

        $job = new SyncBookingToGoogleCalendar($booking->id);
        $this->assertSame(5, $job->tries);
        $this->assertSame([60, 300, 900, 1800], $job->backoff());
    }

    public function test_only_admin_can_manage_calendar_connection_and_retry(): void
    {
        $booking = $this->booking(['status' => 'pending']);
        $office = User::factory()->create(['role' => 'office']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($office)->get(route('admin.integrations.google-calendar.index'))->assertForbidden();
        $this->actingAs($office)->post(route('admin.bookings.calendar.retry', $booking))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.integrations.google-calendar.index'))->assertOk();
    }

    public function test_observer_queues_sync_only_after_relevant_confirmed_change(): void
    {
        Queue::fake();
        $booking = $this->booking(['status' => 'pending']);
        Queue::assertNotPushed(SyncBookingToGoogleCalendar::class);

        $booking->update(['status' => 'confirmed']);

        Queue::assertPushed(SyncBookingToGoogleCalendar::class, fn ($job): bool => $job->bookingId === $booking->id);
        $this->assertSame('queued', $booking->calendarSync()->value('status'));
    }

    private function booking(array $overrides = []): Booking
    {
        $customer = Customer::create([
            'name' => 'Operations Contact',
            'email' => 'customer@example.com',
            'phone' => '0111111111',
        ]);
        $service = Service::create(['code' => 'office-deep-clean', 'name' => 'Office Deep Clean']);
        $staff = Staff::create(['name' => 'Team Alpha']);

        return Booking::create([
            'booking_number' => 'BK-GCAL-1',
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'status' => 'confirmed',
            'scheduled_start' => '2026-11-01 09:00:00',
            'scheduled_end' => '2026-11-01 12:00:00',
            'service_address' => 'Level 8, Example Tower, Kuala Lumpur',
            ...$overrides,
        ]);
    }

    private function connection(): GoogleCalendarConnection
    {
        return GoogleCalendarConnection::create([
            'calendar_id' => 'company@group.calendar.google.com',
            'access_token' => 'encrypted-access-token',
            'refresh_token' => 'encrypted-refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);
    }
}
