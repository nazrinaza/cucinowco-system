<?php

namespace Tests\Feature;

use App\Livewire\SiteVisitForm;
use App\Models\Agent;
use App\Models\AgentActivity;
use App\Models\AgentCommission;
use App\Models\AgentMedia;
use App\Models\AgentMediaFolder;
use App\Models\CommissionPlan;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\ReferralSource;
use App\Models\SiteVisitRequest;
use App\Models\User;
use App\Support\ProgrammeSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AgentToolkitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create();
    }

    private function agent(string $code = 'aina', array $extra = []): Agent
    {
        $agent = Agent::create(['name' => ucfirst($code), 'email' => $code.'@example.com', 'phone' => '012-345 6789', 'location' => 'KL', 'requested_code' => $code, 'password' => 'test-password-1234', 'terms_accepted_at' => now()]);
        $source = ReferralSource::create(['name' => ucfirst($code), 'code' => $code, 'channel' => 'agent', 'is_active' => true]);
        $agent->forceFill(['status' => 'approved', 'email_verified_at' => now(), 'referral_source_id' => $source->id, ...$extra])->save();

        return $agent->fresh();
    }

    private function media(array $attrs = []): AgentMedia
    {
        return AgentMedia::create(['title' => 'Item', 'category' => 'poster', 'is_published' => true, 'caption' => 'Book: {ReferralLink}', ...$attrs]);
    }

    // ── Marketing tools ────────────────────────────────────────────

    public function test_library_shows_only_folders_with_published_content_and_opens_them(): void
    {
        $agent = $this->agent();
        $start = AgentMediaFolder::create(['name' => 'Start here']);
        $videos = AgentMediaFolder::create(['name' => 'Videos']);
        $raw = AgentMediaFolder::create(['name' => 'Raw footage', 'parent_id' => $videos->id]);
        AgentMediaFolder::create(['name' => 'Empty folder']);
        $this->media(['title' => 'Welcome guide', 'folder_id' => $start->id]);
        $this->media(['title' => 'Raw clip', 'folder_id' => $raw->id]);
        $this->media(['title' => 'Hidden draft', 'folder_id' => $start->id, 'is_published' => false]);
        $this->media(['title' => 'Loose poster']);

        $this->actingAs($agent, 'agent')->get(route('agents.media'))->assertOk()
            ->assertSee('Start here')->assertSee('Videos')->assertDontSee('Empty folder')
            ->assertSee('Loose poster')->assertDontSee('Welcome guide');

        $this->get(route('agents.media', ['folder' => $videos->id]))->assertOk()->assertSee('Raw footage')->assertDontSee('Loose poster');
        $this->get(route('agents.media', ['folder' => $raw->id]))->assertOk()->assertSee('Raw clip')->assertSee('Videos');
        $this->get(route('agents.media', ['folder' => $start->id]))->assertOk()->assertSee('Welcome guide')->assertDontSee('Hidden draft');
        $this->get(route('agents.media', ['category' => 'poster']))->assertOk()->assertSee('Welcome guide')->assertSee('Raw clip');
    }

    public function test_link_generator_lists_public_pages_with_the_agents_code(): void
    {
        $this->actingAs($this->agent(), 'agent')->get(route('agents.media'))->assertOk()
            ->assertSee('data-link-builder', false)->assertSee('data-ref="aina"', false)
            ->assertSee('value="/services/office-cleaning"', false);
    }

    public function test_videos_stream_with_range_support(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('agent-media/clip.mp4', str_repeat('v', 2048));
        $video = $this->media(['title' => 'Clip', 'category' => 'video', 'path' => 'agent-media/clip.mp4', 'original_name' => 'clip.mp4', 'mime_type' => 'video/mp4', 'size' => 2048]);

        $this->actingAs($this->agent(), 'agent')->get(route('agents.media'))->assertSee('<video', false);
        $this->get(route('agents.media.preview', $video))->assertOk()->assertHeader('Accept-Ranges', 'bytes');
        $this->withHeader('Range', 'bytes=0-99')->get(route('agents.media.preview', $video))->assertStatus(206);
    }

    public function test_hq_manages_folders_without_losing_content(): void
    {
        $parent = AgentMediaFolder::create(['name' => 'Parent']);
        $child = AgentMediaFolder::create(['name' => 'Child', 'parent_id' => $parent->id]);
        $grandchild = AgentMediaFolder::create(['name' => 'Grandchild', 'parent_id' => $child->id]);
        $item = $this->media(['folder_id' => $child->id]);

        $this->actingAs($this->admin)->post(route('admin.agent-media.folders.store'), ['name' => 'New', 'parent_id' => $parent->id])->assertSessionHasNoErrors();
        $this->patch(route('admin.agent-media.folders.update', $parent), ['name' => 'Parent', 'parent_id' => $grandchild->id])->assertSessionHasErrors('parent_id');

        $this->delete(route('admin.agent-media.folders.destroy', $child))->assertSessionHasNoErrors();
        $this->assertSame($parent->id, $item->fresh()->folder_id);
        $this->assertSame($parent->id, $grandchild->fresh()->parent_id);
        $this->get(route('admin.agent-media.index'))->assertOk()->assertSee('Parent › Grandchild');
    }

    public function test_sales_kit_is_available_to_approved_agents(): void
    {
        $this->actingAs($this->agent(), 'agent')->get(route('agents.sales-kit'))->assertOk()
            ->assertSee('SAY THIS')->assertSee('Office Cleaning')->assertSee('Quick quiz')->assertSee('Sales kit');
    }

    // ── Payout rules ───────────────────────────────────────────────

    public function test_next_payout_date_follows_the_schedule(): void
    {
        $settings = app(ProgrammeSettings::class);
        $this->assertSame('2026-11-28', $settings->nextPayoutDate(CarbonImmutable::parse('2026-11-16'))->toDateString());
        $this->assertSame('2026-12-10', $settings->nextPayoutDate(CarbonImmutable::parse('2026-11-29'))->toDateString());
        $this->assertSame('2026-11-10', $settings->nextPayoutDate(CarbonImmutable::parse('2026-11-10'))->toDateString());
    }

    public function test_hq_updates_settings_and_pays_all_approved_rows_in_one_transfer(): void
    {
        $this->actingAs($this->admin)->patch(route('admin.commissions.settings'), ['payout_minimum' => '100', 'payout_days' => '5, 20', 'customer_discount_percent' => '5'])->assertSessionHasNoErrors();
        $this->patch(route('admin.commissions.settings'), ['payout_minimum' => '100', 'payout_days' => '31', 'customer_discount_percent' => '5'])->assertSessionHasErrors('payout_days');
        $this->assertSame([5, 20], app(ProgrammeSettings::class)->payoutDays());

        $agent = $this->agent();
        $invoice = Invoice::create(['invoice_number' => 'I-1', 'customer_id' => Customer::create(['name' => 'C', 'phone' => '0199999999'])->id, 'status' => 'paid', 'subtotal' => 100, 'total' => 100, 'balance' => 0, 'issued_at' => today(), 'due_at' => today()]);
        foreach (['direct', 'introducer'] as $type) {
            AgentCommission::create(['agent_id' => $agent->id, 'invoice_id' => $invoice->id, 'type' => $type, 'basis' => 'net_sales', 'basis_amount' => 100, 'rate_percent' => 40, 'amount' => 40, 'status' => 'approved']);
        }

        $this->get(route('admin.commissions.index'))->assertOk()->assertSee('Payout queue')->assertSee('No bank details');
        $this->post(route('admin.commissions.pay-agent', $agent), ['payout_reference' => 'IBG-1'])->assertSessionHasErrors('payout_reference');

        $agent->forceFill(['payout_bank' => 'maybank', 'payout_account_name' => 'AINA', 'payout_account_number' => '1122334455'])->save();
        $this->get(route('admin.commissions.index'))->assertSee('Below minimum');
        $this->post(route('admin.commissions.pay-agent', $agent), ['payout_reference' => 'IBG-1'])->assertSessionHasNoErrors();

        $this->assertSame(2, AgentCommission::where('status', 'paid')->where('payout_reference', 'IBG-1')->count());
        $this->assertSame('80.00', AgentActivity::where('action', 'commission.payout')->sole()->details['amount']);
    }

    public function test_agent_dashboard_shows_payout_schedule(): void
    {
        $this->actingAs($this->agent(), 'agent')->get(route('agents.dashboard'))->assertOk()
            ->assertSee('Next payout')->assertSee('10th and 28th')->assertSee('minimum RM 50.00');
    }

    public function test_agents_earn_nothing_when_referring_themselves(): void
    {
        CommissionPlan::default()->update(['rate_percent' => 10]);
        $agent = $this->agent();
        $customer = Customer::create(['name' => 'Aina herself', 'phone' => '+60 12 345 6789']);
        $lead = SiteVisitRequest::create(['reference_number' => 'SV-S', 'customer_id' => $customer->id, 'referral_source_id' => $agent->referral_source_id, 'referral_code' => 'aina', 'space_type' => 'office', 'status' => 'completed', 'site_address' => 'x']);
        $quote = Quote::create(['quote_number' => 'Q-S', 'customer_id' => $customer->id, 'site_visit_request_id' => $lead->id, 'status' => 'accepted']);
        $invoice = Invoice::create(['invoice_number' => 'I-S', 'customer_id' => $customer->id, 'quote_id' => $quote->id, 'status' => 'sent', 'subtotal' => 500, 'total' => 500, 'amount_paid' => 0, 'balance' => 500, 'issued_at' => today(), 'due_at' => today()]);

        $this->actingAs($this->admin)->post(route('admin.invoices.payments', $invoice), ['amount' => 500, 'method' => 'bank_transfer', 'paid_at' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->post(route('admin.commissions.backfill'));

        $this->assertDatabaseCount('agent_commissions', 0);
        $this->assertSame(1, AgentActivity::where('action', 'commission.self_referral_blocked')->count());
    }

    // ── Customer discount code ─────────────────────────────────────

    private function submitVisit(string $agentCode = '')
    {
        return Livewire::test(SiteVisitForm::class)->set('cleanTypes', ['general_cleaning'])->set('spaceType', 'office')->set('name', 'Customer')
            ->set('phone', '0111111111')->set('email', 'customer@example.com')->set('preferredDate', now()->addDay()->format('Y-m-d'))
            ->set('preferredTimeSlot', 'morning')->set('agentCode', $agentCode)->call('submit');
    }

    public function test_typed_agent_code_credits_the_agent_and_records_the_discount(): void
    {
        $agent = $this->agent();
        app(ProgrammeSettings::class)->update(['customer_discount_percent' => '5']);

        $this->submitVisit(' AINA ')->assertHasNoErrors();

        $lead = SiteVisitRequest::sole();
        $this->assertSame($agent->referral_source_id, $lead->referral_source_id);
        $this->assertSame('aina', $lead->promo_code);
        $this->assertSame('5.00', (string) $lead->promo_discount_percent);
        $this->actingAs($this->admin)->get(route('admin.site-visits.show', $lead))->assertOk()->assertSee('5% customer discount promised');
    }

    public function test_unknown_code_is_rejected_and_zero_discount_still_credits(): void
    {
        $agent = $this->agent();
        $this->submitVisit('nobody')->assertHasErrors('agentCode');
        $this->assertDatabaseCount('site_visit_requests', 0);

        $this->submitVisit('aina')->assertHasNoErrors();
        $lead = SiteVisitRequest::sole();
        $this->assertSame($agent->referral_source_id, $lead->referral_source_id);
        $this->assertNull($lead->promo_discount_percent);
    }

    public function test_code_from_an_agent_link_is_prefilled(): void
    {
        $this->agent();
        $this->withSession(['cucinow.referral_code' => 'aina']);

        Livewire::test(SiteVisitForm::class)->assertSet('agentCode', 'aina');
    }
}
