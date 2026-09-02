<?php

namespace Tests\Feature;

use App\Livewire\QuoteEstimator;
use App\Models\Service;
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
}
