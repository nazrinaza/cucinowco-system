<?php

namespace Tests\Feature;

use App\Livewire\SiteVisitForm;
use App\Models\Service;
use App\Models\Customer;
use App\Models\SiteVisitRequest;
use App\Mail\SiteVisitConfirmationMail;
use App\Mail\NewSiteVisitNotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteVisitFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_request_a_free_site_visit_without_creating_a_quote(): void
    {
        Mail::fake();
        Customer::create([
            'phone' => '0123456789', 'name' => 'Aina',
            'company_name' => 'Existing Company', 'address' => 'Existing Address', 'postcode' => '40160',
        ]);
        $service = Service::create([
            'code' => 'office-cleaning', 'name' => 'Office Cleaning', 'base_price' => 280,
            'unit' => 'job', 'is_active' => true, 'sort_order' => 1,
        ]);

        Livewire::test(SiteVisitForm::class)
            ->set('cleanTypes', ['general_cleaning', 'carpet_cleaning'])
            ->set('spaceType', 'office')
            ->set('name', 'Aina Rahman')
            ->set('phone', '0123456789')
            ->set('email', 'aina@example.com')
            ->set('preferredDate', now()->addDay()->format('Y-m-d'))
            ->set('preferredTimeSlot', 'morning')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('customers', [
            'phone' => '0123456789', 'name' => 'Aina Rahman',
            'company_name' => 'Existing Company', 'address' => 'Existing Address', 'postcode' => '40160',
        ]);
        $this->assertDatabaseHas('site_visit_requests', [
            'service_id' => null, 'source' => 'website', 'status' => 'new',
            'space_type' => 'office', 'site_address' => '',
        ]);
        $this->assertDatabaseCount('quotes', 0);
        $visit = SiteVisitRequest::firstOrFail();
        $this->assertSame(['general_cleaning', 'carpet_cleaning'], $visit->clean_types);
        $this->assertSame('General Cleaning, Carpet Cleaning', $visit->clean_types_label);
        foreach ([new SiteVisitConfirmationMail($visit), new NewSiteVisitNotificationMail($visit)] as $mail) {
            $html = $mail->render();
            $this->assertStringContainsString('General Cleaning, Carpet Cleaning', $html);
            $this->assertStringContainsString('Corporate Office', $html);
        }
    }

    public function test_clean_types_are_required_and_invalid_values_are_rejected(): void
    {
        Mail::fake();
        Livewire::test(SiteVisitForm::class)
            ->set('name', 'Test Visitor')
            ->set('email', 'visitor@example.com')
            ->set('phone', '0123456789')
            ->call('submit')->assertHasErrors(['cleanTypes'])
            ->set('cleanTypes', ['not-a-clean-type'])
            ->call('submit')->assertHasErrors(['cleanTypes.0'])
            ->set('cleanTypes', ['carpet_cleaning', 'carpet_cleaning'])
            ->call('submit')->assertHasErrors(['cleanTypes.0']);

        $this->assertDatabaseCount('site_visit_requests', 0);
        Mail::assertNothingQueued();
    }

    public function test_form_shows_six_space_and_clean_options_without_old_fields(): void
    {
        $form = Livewire::test(SiteVisitForm::class);
        foreach (array_merge(config('site_visits.spaces'), config('site_visits.clean_types')) as $label) {
            $form->assertSee($label);
        }
        foreach (['companyName', 'siteAddress', 'postcode', 'notes', 'serviceId'] as $field) {
            $form->assertDontSee('wire:model="'.$field.'"', false);
        }
    }

    public function test_email_is_required_and_must_be_valid_before_creating_a_request(): void
    {
        Mail::fake();
        Livewire::test(SiteVisitForm::class)
            ->set('name', 'Test Visitor')
            ->set('phone', '0123456789')
            ->set('cleanTypes', ['general_cleaning'])
            ->set('email', '')
            ->call('submit')->assertHasErrors(['email' => 'required'])
            ->set('email', 'invalid-email')
            ->call('submit')->assertHasErrors(['email' => 'email'])
            ->assertSet('submitted', false);

        $this->assertDatabaseCount('site_visit_requests', 0);
        $this->assertDatabaseCount('customers', 0);
        Mail::assertNothingQueued();
    }
}
