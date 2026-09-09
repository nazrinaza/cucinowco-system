<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Service;
use App\Models\SiteVisitRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteVisitCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_site_visit_page_contains_the_fullcalendar_view(): void
    {
        $admin = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.site-visits.index'))
            ->assertOk()
            ->assertSee('Site visit calendar')
            ->assertSee('data-full-calendar', false)
            ->assertSee(route('admin.site-visits.calendar-events'), false)
            ->assertSee('All site visit requests');
    }

    public function test_calendar_feed_returns_only_dated_visits_in_the_requested_range(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $service = Service::create([
            'code' => 'corporate-office',
            'name' => 'Corporate Office Cleaning',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'name' => 'Calendar Client',
            'company_name' => 'Calendar Sdn Bhd',
            'phone' => '0111111111',
        ]);

        $scheduled = $this->createSiteVisit($customer, $service, [
            'reference_number' => 'SV-202609-SCHEDULED',
            'status' => 'scheduled',
            'preferred_date' => '2026-09-12',
            'preferred_time_slot' => 'morning',
        ]);
        $this->createSiteVisit($customer, $service, [
            'reference_number' => 'SV-202610-OUTSIDE',
            'status' => 'completed',
            'preferred_date' => '2026-10-03',
        ]);
        $this->createSiteVisit($customer, $service, [
            'reference_number' => 'SV-FLEXIBLE',
            'status' => 'new',
            'preferred_date' => null,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.site-visits.calendar-events', [
                'start' => '2026-09-01',
                'end' => '2026-10-01',
            ]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', (string) $scheduled->id)
            ->assertJsonPath('0.title', 'Morning · Calendar Client')
            ->assertJsonPath('0.start', '2026-09-12')
            ->assertJsonPath('0.allDay', true)
            ->assertJsonPath('0.color', '#405b7f')
            ->assertJsonPath('0.contrastColor', '#ffffff')
            ->assertJsonPath('0.className', 'cucinow-calendar-event site-visit-event-scheduled')
            ->assertJsonPath('0.extendedProps.reference', 'SV-202609-SCHEDULED')
            ->assertJsonPath('0.extendedProps.service', 'Corporate Office Cleaning')
            ->assertJsonPath('0.extendedProps.status', 'Scheduled')
            ->assertJsonPath('0.url', route('admin.site-visits.show', $scheduled));
    }

    public function test_calendar_feed_respects_the_site_visit_filters(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $service = Service::create(['code' => 'grand-hall', 'name' => 'Grand Hall Cleaning', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Filtered Client', 'phone' => '0122222222']);

        $this->createSiteVisit($customer, $service, [
            'reference_number' => 'SV-NEW',
            'status' => 'new',
            'preferred_date' => '2026-09-10',
        ]);
        $completed = $this->createSiteVisit($customer, $service, [
            'reference_number' => 'SV-COMPLETED',
            'status' => 'completed',
            'preferred_date' => '2026-09-11',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.site-visits.calendar-events', [
                'start' => '2026-09-01',
                'end' => '2026-10-01',
                'status' => 'completed',
                'q' => 'Filtered',
            ]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', (string) $completed->id)
            ->assertJsonPath('0.color', '#2b8a62')
            ->assertJsonPath('0.className', 'cucinow-calendar-event site-visit-event-completed');
    }

    public function test_calendar_feed_requires_authentication_and_valid_dates(): void
    {
        $this->get(route('admin.site-visits.calendar-events', [
            'start' => '2026-09-01',
            'end' => '2026-10-01',
        ]))->assertRedirect('/admin/login');

        $admin = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->getJson(route('admin.site-visits.calendar-events', [
                'start' => '2026-10-01',
                'end' => '2026-09-01',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end');
    }

    private function createSiteVisit(Customer $customer, Service $service, array $overrides): SiteVisitRequest
    {
        return SiteVisitRequest::create(array_merge([
            'reference_number' => 'SV-'.fake()->unique()->numerify('########'),
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'new',
            'space_type' => 'office',
            'preferred_date' => '2026-09-01',
            'preferred_time_slot' => 'afternoon',
            'site_address' => 'Shah Alam, Selangor',
        ], $overrides));
    }
}
