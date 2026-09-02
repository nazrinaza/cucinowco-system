<?php

namespace Tests\Feature;

use App\Livewire\SiteVisitForm;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteVisitFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_request_a_free_site_visit_without_creating_a_quote(): void
    {
        $service = Service::create([
            'code' => 'office-cleaning', 'name' => 'Office Cleaning', 'base_price' => 280,
            'unit' => 'job', 'is_active' => true, 'sort_order' => 1,
        ]);

        Livewire::test(SiteVisitForm::class)
            ->set('serviceId', $service->id)
            ->set('spaceType', 'office')
            ->set('companyName', 'Example Sdn Bhd')
            ->set('name', 'Aina Rahman')
            ->set('phone', '0123456789')
            ->set('email', 'aina@example.com')
            ->set('preferredDate', now()->addDay()->format('Y-m-d'))
            ->set('preferredTimeSlot', 'morning')
            ->set('siteAddress', 'Example Tower, Jalan Example')
            ->set('postcode', '40160')
            ->set('notes', 'Please contact reception on arrival.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('customers', [
            'phone' => '0123456789', 'name' => 'Aina Rahman', 'company_name' => 'Example Sdn Bhd',
        ]);
        $this->assertDatabaseHas('site_visit_requests', [
            'service_id' => $service->id, 'source' => 'website', 'status' => 'new',
            'space_type' => 'office', 'postcode' => '40160',
        ]);
        $this->assertDatabaseCount('quotes', 0);
    }
}
