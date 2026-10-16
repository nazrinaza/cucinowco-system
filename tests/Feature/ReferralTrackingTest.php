<?php

namespace Tests\Feature;

use App\Livewire\SiteVisitForm;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\ReferralSource;
use App\Models\SiteVisitRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ReferralTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_referral_source_and_open_the_qr_library(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.referrals.store'), [
            'name' => 'Ahmad Agent',
            'code' => 'Ahmad',
            'channel' => 'agent',
        ])->assertRedirect();

        $this->assertDatabaseHas('referral_sources', [
            'name' => 'Ahmad Agent',
            'code' => 'ahmad',
            'channel' => 'agent',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.referrals.index'))
            ->assertOk()
            ->assertSee('Referral QR generator')
            ->assertSee('?ref=ahmad');
    }

    public function test_valid_referral_is_remembered_and_attached_to_a_site_visit_lead(): void
    {
        Mail::fake();
        $source = ReferralSource::create([
            'name' => 'Ahmad',
            'code' => 'ahmad',
            'channel' => 'agent',
            'is_active' => true,
        ]);

        $this->get('/?ref=ahmad')
            ->assertOk()
            ->assertSessionHas('cucinow.referral_source_id', $source->id)
            ->assertSessionHas('cucinow.referral_code', 'ahmad');

        Livewire::test(SiteVisitForm::class)
            ->set('cleanTypes', ['general_cleaning'])
            ->set('spaceType', 'office')
            ->set('name', 'Referral Lead')
            ->set('phone', '01112345678')
            ->set('email', 'lead@example.com')
            ->set('preferredDate', now()->addDay()->format('Y-m-d'))
            ->set('preferredTimeSlot', 'morning')
            ->call('submit')
            ->assertHasNoErrors();

        $lead = SiteVisitRequest::with('referralSource')->firstOrFail();
        $this->assertSame($source->id, $lead->referral_source_id);
        $this->assertSame('ahmad', $lead->referral_code);
        $this->assertSame('Ahmad · Agent', $lead->referral_label);
        $this->assertSame(1, $source->fresh()->visit_count);
        $this->assertSame(1, $source->fresh()->lead_count);
    }

    public function test_leads_without_attribution_are_marked_direct_across_documents(): void
    {
        Mail::fake();
        Livewire::test(SiteVisitForm::class)
            ->set('cleanTypes', ['general_cleaning'])
            ->set('spaceType', 'office')
            ->set('name', 'Direct Lead')
            ->set('phone', '01187654321')
            ->set('email', 'direct@example.com')
            ->set('preferredDate', now()->addDay()->format('Y-m-d'))
            ->set('preferredTimeSlot', 'flexible')
            ->call('submit')
            ->assertHasNoErrors();

        $lead = SiteVisitRequest::firstOrFail();
        $this->assertSame('direct', $lead->referral_code);
        $this->assertSame('Direct', $lead->referral_label);

        $quote = Quote::create([
            'quote_number' => 'Q-REF-DIRECT',
            'customer_id' => Customer::firstOrFail()->id,
            'status' => 'draft',
        ]);
        $lead->update(['quote_id' => $quote->id]);

        $this->assertSame('Direct', $quote->fresh()->referral_label);
    }

    public function test_paused_or_unknown_codes_do_not_receive_attribution(): void
    {
        ReferralSource::create([
            'name' => 'Paused Campaign',
            'code' => 'paused',
            'channel' => 'campaign',
            'is_active' => false,
        ]);

        $this->get('/?ref=paused')->assertSessionMissing('cucinow.referral_source_id');
        $this->get('/?ref=unknown')->assertSessionMissing('cucinow.referral_source_id');
    }
}
