<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\CommissionPlan;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\ReferralSource;
use App\Models\SiteVisitRequest;
use App\Models\User;
use App\Support\AgentCommissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AgentCommissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create();
    }

    private function agent(string $code, ?ReferralSource $introducer = null): Agent
    {
        $agent = Agent::create(['name' => ucfirst($code), 'email' => $code.'@example.com', 'phone' => '601112345678', 'location' => 'Selangor', 'requested_code' => $code, 'password' => 'test-password-1234', 'terms_accepted_at' => now()]);
        $source = ReferralSource::create(['name' => $agent->name, 'code' => $code, 'channel' => 'agent', 'is_active' => true]);
        $agent->forceFill(['status' => 'approved', 'email_verified_at' => now(), 'referral_source_id' => $source->id, 'introducer_source_id' => $introducer?->id])->save();

        return $agent->fresh();
    }

    /** An unpaid RM 1,080 invoice (RM 1,100 subtotal, RM 100 discount, RM 80 tax) referred by $agent. */
    private function referredInvoice(Agent $agent, string $ref = '1'): Invoice
    {
        $customer = Customer::create(['name' => 'Customer '.$ref, 'phone' => '012345678'.$ref]);
        $lead = SiteVisitRequest::create(['reference_number' => 'SV-C-'.$ref, 'customer_id' => $customer->id, 'referral_source_id' => $agent->referral_source_id, 'referral_code' => $agent->requested_code, 'space_type' => 'office', 'status' => 'completed', 'site_address' => 'Site']);
        $quote = Quote::create(['quote_number' => 'Q-C-'.$ref, 'customer_id' => $customer->id, 'site_visit_request_id' => $lead->id, 'status' => 'accepted']);

        return Invoice::create(['invoice_number' => 'I-C-'.$ref, 'customer_id' => $customer->id, 'quote_id' => $quote->id, 'status' => 'sent', 'subtotal' => 1100, 'discount' => 100, 'tax_amount' => 80, 'total' => 1080, 'amount_paid' => 0, 'balance' => 1080, 'issued_at' => today(), 'due_at' => today()]);
    }

    private function pay(Invoice $invoice, float $amount): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('admin.invoices.payments', $invoice), ['amount' => $amount, 'method' => 'bank_transfer', 'paid_at' => today()->toDateString()]);
    }

    private function setDefaultRate(string $rate, string $introducer = '0', string $basis = 'net_sales'): void
    {
        CommissionPlan::default()->update(['rate_percent' => $rate, 'introducer_rate_percent' => $introducer, 'basis' => $basis]);
    }

    public function test_default_plan_starts_at_zero_so_nothing_is_earned_until_hq_sets_a_rate(): void
    {
        $invoice = $this->referredInvoice($this->agent('aina'));
        $this->pay($invoice, 1080)->assertSessionHasNoErrors();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertDatabaseCount('agent_commissions', 0);
    }

    public function test_full_payment_records_one_pending_commission_on_net_sales(): void
    {
        $this->setDefaultRate('10');
        $agent = $this->agent('aina');
        $invoice = $this->referredInvoice($agent);

        $this->pay($invoice, 500)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('agent_commissions', 0);

        $this->pay($invoice, 580)->assertSessionHasNoErrors();
        $row = AgentCommission::sole();
        $this->assertSame($agent->id, $row->agent_id);
        $this->assertSame('1000.00', (string) $row->basis_amount);
        $this->assertSame('10.00', (string) $row->rate_percent);
        $this->assertSame('100.00', (string) $row->amount);
        $this->assertSame('pending', $row->status);

        app(AgentCommissions::class)->recordFor($invoice->fresh());
        $this->assertDatabaseCount('agent_commissions', 1);
    }

    public function test_total_basis_personal_override_and_introducer_override(): void
    {
        $this->setDefaultRate('10', '2', 'total');
        $introducer = $this->agent('leader');
        $agent = $this->agent('aina', $introducer->referralSource);
        $agent->forceFill(['commission_rate_override' => '5'])->save();

        $this->pay($this->referredInvoice($agent), 1080)->assertSessionHasNoErrors();

        $direct = AgentCommission::where('type', 'direct')->sole();
        $this->assertSame($agent->id, $direct->agent_id);
        $this->assertSame('54.00', (string) $direct->amount);
        $override = AgentCommission::where('type', 'introducer')->sole();
        $this->assertSame($introducer->id, $override->agent_id);
        $this->assertSame('21.60', (string) $override->amount);
    }

    public function test_plan_changes_do_not_rewrite_earned_commissions(): void
    {
        $this->setDefaultRate('10');
        $agent = $this->agent('aina');
        $this->pay($this->referredInvoice($agent, '1'), 1080);
        $this->setDefaultRate('20');
        $this->pay($this->referredInvoice($agent, '2'), 1080);

        $this->assertEqualsCanonicalizing(['100.00', '200.00'], AgentCommission::pluck('amount')->map(fn ($a) => (string) $a)->all());
    }

    public function test_agent_gets_two_decimals_and_sen_fraction_goes_to_company_reserve(): void
    {
        // RM 333.33 × 7.5% = RM 24.999750 exactly → agent RM 24.99, reserve RM 0.009750.
        $this->setDefaultRate('7.5');
        $invoice = $this->referredInvoice($this->agent('aina'));
        $invoice->update(['subtotal' => 333.33, 'discount' => 0, 'tax_amount' => 0, 'total' => 333.33, 'balance' => 333.33]);

        $this->pay($invoice, 333.33)->assertSessionHasNoErrors();

        $row = AgentCommission::sole();
        $this->assertSame('24.99', (string) $row->amount);
        $this->assertSame('0.009750', (string) $row->rounding_remainder);
        $this->assertSame('0.009750', app(AgentCommissions::class)->roundingReserve());

        $row->update(['status' => 'void']);
        $this->assertSame('0.000000', app(AgentCommissions::class)->roundingReserve());
    }

    public function test_direct_invoices_earn_nothing(): void
    {
        $this->setDefaultRate('10');
        $customer = Customer::create(['name' => 'Walk in', 'phone' => '0199999999']);
        $quote = Quote::create(['quote_number' => 'Q-D', 'customer_id' => $customer->id, 'status' => 'accepted']);
        $invoice = Invoice::create(['invoice_number' => 'I-D', 'customer_id' => $customer->id, 'quote_id' => $quote->id, 'status' => 'sent', 'subtotal' => 500, 'total' => 500, 'balance' => 500, 'issued_at' => today(), 'due_at' => today()]);

        $this->pay($invoice, 500)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('agent_commissions', 0);
    }

    public function test_payment_cannot_exceed_balance(): void
    {
        $invoice = $this->referredInvoice($this->agent('aina'));
        $this->pay($invoice, 1081)->assertSessionHasErrors('amount');
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_hq_workflow_approve_pay_and_paid_is_final(): void
    {
        $this->setDefaultRate('10');
        $this->pay($this->referredInvoice($this->agent('aina')), 1080);
        $row = AgentCommission::sole();

        $this->actingAs($this->admin)->patch(route('admin.commissions.update', $row), ['status' => 'paid', 'payout_reference' => 'X'])->assertSessionHasErrors('status');
        $this->patch(route('admin.commissions.update', $row), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->patch(route('admin.commissions.update', $row), ['status' => 'paid'])->assertSessionHasErrors('payout_reference');
        $this->patch(route('admin.commissions.update', $row), ['status' => 'paid', 'payout_reference' => 'DUITNOW-123'])->assertSessionHasNoErrors();
        $this->patch(route('admin.commissions.update', $row), ['status' => 'void'])->assertSessionHasErrors('status');

        $row->refresh();
        $this->assertSame('paid', $row->status);
        $this->assertNotNull($row->paid_at);
        $this->assertSame($this->admin->id, $row->approved_by_user_id);
    }

    public function test_backfill_picks_up_invoices_paid_before_the_rate_was_set(): void
    {
        $this->pay($this->referredInvoice($this->agent('aina')), 1080);
        $this->assertDatabaseCount('agent_commissions', 0);

        $this->setDefaultRate('10');
        $this->actingAs($this->admin)->post(route('admin.commissions.backfill'))->assertSessionHas('success');
        $this->post(route('admin.commissions.backfill'));
        $this->assertDatabaseCount('agent_commissions', 1);
    }

    public function test_pages_render_and_agent_sees_only_own_earnings(): void
    {
        $this->setDefaultRate('10');
        $agent = $this->agent('aina');
        $other = $this->agent('budi');
        $this->pay($this->referredInvoice($agent, '1'), 1080);
        $this->pay($this->referredInvoice($other, '2'), 1080);
        AgentCommission::where('agent_id', $other->id)->update(['amount' => 777.77]);

        $this->actingAs($this->admin)->get(route('admin.commissions.index'))->assertOk()->assertSee('RM 100.00')->assertSee('I-C-1');
        $this->get(route('admin.agents.show', $agent))->assertOk()->assertSee('Commission settings');
        $this->get(route('admin.commissions.export'))->assertOk()->assertDownload();

        $this->actingAs($agent, 'agent')->get(route('agents.dashboard'))->assertOk()
            ->assertSee('RM 100.00')->assertSee('10% commission rate')->assertDontSee('777.77');
    }

    public function test_sen_conversion_is_exact(): void
    {
        $this->assertSame(123456, AgentCommissions::toSen('1234.56'));
        $this->assertSame(1010, AgentCommissions::toSen(10.1));
        $this->assertSame(-550, AgentCommissions::toSen('-5.50'));
        $this->assertSame('0.07', AgentCommissions::fromSen(7));
    }
}
