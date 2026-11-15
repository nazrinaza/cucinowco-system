<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentActivity;
use App\Models\ReferralSource;
use App\Models\User;
use App\Support\MalaysianBanks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgentPayoutDetailsTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = Agent::create(['name' => 'Aina', 'email' => 'aina@example.com', 'phone' => '0123456789', 'location' => 'Selangor', 'requested_code' => 'aina', 'password' => 'test-password-1234', 'terms_accepted_at' => now()]);
        $source = ReferralSource::create(['name' => 'Aina', 'code' => 'aina', 'channel' => 'agent', 'is_active' => true]);
        $this->agent->forceFill(['status' => 'approved', 'email_verified_at' => now(), 'referral_source_id' => $source->id])->save();
    }

    private function save(array $overrides = [])
    {
        return $this->actingAs($this->agent, 'agent')->patch(route('agents.account.payout'), [
            'payout_bank' => 'maybank', 'payout_account_name' => "  siti nur'ain  binti ali ",
            'payout_account_number' => '1122-3344 5566', 'payout_password' => 'test-password-1234', ...$overrides,
        ]);
    }

    public function test_agent_saves_bank_details_normalised_and_encrypted(): void
    {
        $this->save()->assertSessionHasNoErrors()->assertSessionHas('success');

        $agent = $this->agent->fresh();
        $this->assertSame('maybank', $agent->payout_bank);
        $this->assertSame("SITI NUR'AIN BINTI ALI", $agent->payout_account_name);
        $this->assertSame('112233445566', $agent->payout_account_number);
        $this->assertTrue($agent->hasPayoutDetails());
        $this->assertSame('•••• 5566', $agent->maskedPayoutAccountNumber());

        $raw = DB::table('agents')->where('id', $agent->id)->value('payout_account_number');
        $this->assertStringNotContainsString('112233445566', $raw);

        $log = AgentActivity::where('action', 'payout.updated')->sole();
        $this->assertSame(['bank' => 'maybank', 'account_last4' => '5566'], $log->details);
    }

    public function test_wrong_password_bad_bank_and_bad_number_are_rejected(): void
    {
        $this->save(['payout_password' => 'wrong-password'])->assertSessionHasErrorsIn('payout', 'payout_password');
        $this->save(['payout_bank' => 'not-a-bank'])->assertSessionHasErrorsIn('payout', 'payout_bank');
        $this->save(['payout_account_number' => '12ab34'])->assertSessionHasErrorsIn('payout', 'payout_account_number');
        $this->save(['payout_account_number' => '12345'])->assertSessionHasErrorsIn('payout', 'payout_account_number');
        $this->save(['payout_account_name' => 'Aina <script>'])->assertSessionHasErrorsIn('payout', 'payout_account_name');

        $this->assertFalse($this->agent->fresh()->hasPayoutDetails());
    }

    public function test_account_page_lists_banks_and_masks_saved_number(): void
    {
        $this->save();

        $this->actingAs($this->agent->fresh(), 'agent')->get(route('agents.account'))->assertOk()
            ->assertSee('Where should HQ pay you?')
            ->assertSee(MalaysianBanks::LIST['bank_islam'])
            ->assertSee('•••• 5566')
            ->assertDontSee('112233445566');
    }

    public function test_dashboard_prompts_until_details_are_added(): void
    {
        $this->actingAs($this->agent, 'agent')->get(route('agents.dashboard'))->assertOk()->assertSee('Add bank details');
        $this->save();
        $this->actingAs($this->agent->fresh(), 'agent')->get(route('agents.dashboard'))->assertOk()->assertSee('Maybank •••• 5566');
    }

    public function test_hq_sees_the_full_account_for_the_transfer(): void
    {
        $this->agent->forceFill(['payout_bank' => 'maybank', 'payout_account_name' => "SITI NUR'AIN BINTI ALI", 'payout_account_number' => '112233445566'])->save();

        $this->actingAs(User::factory()->create())->get(route('admin.agents.show', $this->agent))->assertOk()
            ->assertSee('112233445566')->assertSee("SITI NUR'AIN BINTI ALI");
    }

    public function test_account_number_never_appears_in_serialised_agent(): void
    {
        $this->save();

        $this->assertArrayNotHasKey('payout_account_number', $this->agent->fresh()->toArray());
    }
}
