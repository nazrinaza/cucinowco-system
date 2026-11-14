<?php

namespace Tests\Feature;

use App\Livewire\QuoteEstimator;
use App\Models\Customer;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\SiteVisitRequest;
use App\Models\User;
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

    public function test_estimator_creates_itemised_scope_and_applies_discount(): void
    {
        $service = Service::create([
            'code' => 'office-cleaning', 'name' => 'Office Cleaning', 'base_price' => 280,
            'unit' => 'job', 'is_active' => true, 'sort_order' => 1,
        ]);

        Livewire::test(QuoteEstimator::class)
            ->set('serviceId', $service->id)
            ->set('preferredDate', now()->addDay()->format('Y-m-d'))
            ->set('name', 'Itemised Client')
            ->set('phone', '0123456788')
            ->set('address', 'Itemised Office')
            ->set('postcode', '40160')
            ->set('city', 'Shah Alam')
            ->set('state', 'Selangor')
            ->set('items', [
                ['description' => 'Deep cleaning', 'quantity' => '2.00', 'unit' => 'floor', 'unit_price' => '100.25', 'notes' => '<strong>Detailed scope</strong><script>alert(1)</script>'],
                ['description' => 'Carpet extraction', 'quantity' => '1.50', 'unit' => 'area', 'unit_price' => '100.00', 'notes' => '<ul><li>Meeting rooms</li></ul>'],
            ])
            ->set('discount', '25.00')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('quotes', ['subtotal' => 350.50, 'discount' => 25, 'total' => 325.50]);
        $this->assertDatabaseHas('quote_items', [
            'service_id' => $service->id, 'description' => 'Deep cleaning', 'quantity' => 2,
            'unit' => 'floor', 'unit_price' => 100.25, 'amount' => 200.50,
        ]);
        $this->assertDatabaseHas('quote_items', [
            'service_id' => null, 'description' => 'Carpet extraction', 'quantity' => 1.50,
            'unit' => 'area', 'unit_price' => 100, 'amount' => 150,
        ]);
        $this->assertStringNotContainsString('<script', (string) QuoteItem::where('description', 'Deep cleaning')->value('notes'));
    }

    public function test_estimator_rejects_a_discount_above_the_services_subtotal(): void
    {
        $service = Service::create([
            'code' => 'office-cleaning', 'name' => 'Office Cleaning', 'base_price' => 100,
            'unit' => 'job', 'is_active' => true, 'sort_order' => 1,
        ]);

        Livewire::test(QuoteEstimator::class)
            ->set('serviceId', $service->id)
            ->set('preferredDate', now()->addDay()->format('Y-m-d'))
            ->set('name', 'Discount Client')
            ->set('phone', '0123456787')
            ->set('address', 'Discount Office')
            ->set('postcode', '40160')
            ->set('city', 'Shah Alam')
            ->set('state', 'Selangor')
            ->set('discount', '101.00')
            ->call('submit')
            ->assertHasErrors(['discount']);

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_linked_site_assessment_checklist_is_visible_while_building_the_quote(): void
    {
        $service = Service::create([
            'code' => 'office-cleaning', 'name' => 'Office Cleaning', 'base_price' => 280,
            'unit' => 'job', 'is_active' => true, 'sort_order' => 1,
        ]);
        $customer = Customer::create(['name' => 'Assessment Client', 'phone' => '0123456786']);
        $siteVisit = SiteVisitRequest::create([
            'reference_number' => 'SV-ASSESSMENT', 'customer_id' => $customer->id, 'service_id' => $service->id,
            'status' => 'completed', 'space_type' => 'office', 'preferred_date' => now()->addDay(),
            'preferred_time_slot' => 'morning', 'site_address' => 'Assessment Office', 'postcode' => '40160',
            'assessment_data' => [
                'site_size_sqft' => 12500, 'floor_count' => 3, 'premises_type' => 'corporate_office',
                'tools' => ['floor_scrubber', 'wet_dry_vacuum'], 'ppe' => ['safety_boots'],
                'forklift_required' => true, 'expected_duration_hours' => 8,
                'access_notes' => 'Use loading bay B.', 'assessment_notes' => 'Start from the upper floor.',
            ],
        ]);

        Livewire::test(QuoteEstimator::class, ['siteVisit' => $siteVisit])
            ->assertSee('Site Assessment Checklist')
            ->assertSee('12,500 sq ft')
            ->assertSee('Floor scrubber')
            ->assertSee('Use loading bay B.')
            ->assertDontSee('Preliminary estimate');
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

        $this->actingAs(User::factory()->create(['is_active' => true]));
        $quote = $siteVisit->quotes()->firstOrFail();
        $quote->update(['deposit_percentage' => 35]);
        $this->get(route('admin.quotes.show', $quote))->assertOk()
            ->assertSee('Owning site-visit session')->assertSee('SV-MULTI-QUOTE')
            ->assertSee('Project confirmation requires a 35% deposit.')
            ->assertDontSee('Requested schedule')
            ->assertDontSee('requires a 40% deposit');
        $this->get(route('admin.quotes.index'))->assertOk()
            ->assertSee('Site visit session')->assertSee('SV-MULTI-QUOTE');
    }
}
