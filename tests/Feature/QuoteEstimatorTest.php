<?php

namespace Tests\Feature;

use App\Livewire\QuoteEstimator;
use App\Models\Service;
use App\Models\Customer;
use App\Models\SiteVisitRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteEstimatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_unavailable_services_are_not_shown_in_the_quote_estimator(): void
    {
        Service::create([
            'code' => 'home-cleaning', 'name' => 'Home Cleaning', 'base_price' => 120,
            'unit' => 'job', 'is_active' => true, 'sort_order' => 1,
        ]);

        Service::create([
            'code' => 'kitchen-hood', 'name' => 'Kitchen & Hood Cleaning', 'base_price' => 500,
            'unit' => 'job', 'is_active' => true, 'sort_order' => 2,
        ]);

        $office = Service::create([
            'code' => 'office-cleaning', 'name' => 'Office Cleaning', 'base_price' => 280,
            'unit' => 'job', 'is_active' => true, 'sort_order' => 3,
        ]);

        Livewire::test(QuoteEstimator::class)
            ->assertSet('serviceId', $office->id)
            ->assertDontSee('Home Cleaning')
            ->assertDontSee('Kitchen &amp; Hood Cleaning', false)
            ->assertSee('Office Cleaning');
    }

    public function test_a_customer_can_submit_a_quote_request(): void
    {
        $service = Service::create([
            'code' => 'office-cleaning', 'name' => 'Office Cleaning', 'base_price' => 280,
            'unit' => 'job', 'is_active' => true, 'sort_order' => 1,
        ]);

        Livewire::test(QuoteEstimator::class)
            ->set('serviceId', $service->id)
            ->set('propertyType', 'office')
            ->set('sizeBand', 'under_1000')
            ->set('frequency', 'one_off')
            ->set('preferredDate', now()->addDay()->format('Y-m-d'))
            ->set('preferredTimeSlot', 'morning')
            ->set('name', 'Aina Rahman')
            ->set('phone', '0123456789')
            ->set('email', 'aina@example.com')
            ->set('address', 'Office Example, Jalan Example')
            ->set('postcode', '40160')
            ->set('city', 'Shah Alam')
            ->set('state', 'Selangor')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('customers', ['phone' => '0123456789', 'name' => 'Aina Rahman']);
        $this->assertDatabaseHas('quotes', ['status' => 'draft', 'source' => 'admin', 'property_type' => 'office', 'total' => 280]);
        $this->assertDatabaseHas('quote_items', ['description' => 'Office Cleaning', 'amount' => 280]);
    }

    public function test_one_site_visit_can_have_multiple_quotations(): void
    {
        $service = Service::create([
            'code' => 'office-cleaning', 'name' => 'Office Cleaning', 'base_price' => 280,
            'unit' => 'job', 'is_active' => true, 'sort_order' => 1,
        ]);
        $customer = Customer::create([
            'name' => 'Multiple Quote Client', 'phone' => '0123456000', 'email' => 'multiple@example.com',
            'address' => 'Example Office', 'postcode' => '40160', 'city' => 'Shah Alam', 'state' => 'Selangor',
        ]);
        $siteVisit = SiteVisitRequest::create([
            'reference_number' => 'SV-MULTI-QUOTE', 'customer_id' => $customer->id, 'service_id' => $service->id,
            'status' => 'completed', 'space_type' => 'office', 'preferred_date' => now()->addDay(),
            'preferred_time_slot' => 'morning', 'site_address' => 'Example Office', 'postcode' => '40160',
        ]);

        foreach (['under_1000', '1000_2000'] as $sizeBand) {
            Livewire::test(QuoteEstimator::class, ['siteVisit' => $siteVisit])
                ->set('sizeBand', $sizeBand)
                ->set('city', 'Shah Alam')
                ->call('submit')
                ->assertHasNoErrors()
                ->assertSet('submitted', true);
        }

        $siteVisit->refresh();
        $this->assertSame(2, $siteVisit->quotes()->count());
        $this->assertSame($siteVisit->quotes()->latest('id')->value('id'), $siteVisit->quote_id);
        $this->assertSame([$siteVisit->id], $siteVisit->quotes()->pluck('site_visit_request_id')->unique()->all());
    }
}
