<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\CommissionPlan;
use App\Models\ReferralSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentHowItWorksPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_page_with_apply_links_and_it_is_indexable(): void
    {
        $this->get(route('agents.how-it-works'))->assertOk()
            ->assertSee('Four steps from introduction to payout.')
            ->assertSee('Questions, answered.')
            ->assertSee('href="'.route('agents.register').'"', false)
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('?ref=yourname');
    }

    public function test_other_agent_pages_stay_out_of_search_engines(): void
    {
        $this->get(route('agents.register'))->assertOk()->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    public function test_zero_rate_hides_the_worked_example(): void
    {
        $this->get(route('agents.how-it-works'))->assertOk()
            ->assertSee('Rate confirmed on approval')
            ->assertDontSee('You earn</dt>', false);
    }

    public function test_worked_example_uses_the_live_default_rate(): void
    {
        CommissionPlan::default()->update(['rate_percent' => 7.5, 'introducer_rate_percent' => 2, 'basis' => 'net_sales']);

        $this->get(route('agents.how-it-works'))->assertOk()
            ->assertSee('× 7.5%')
            ->assertSee('RM 225.00')
            ->assertSee('introducer reward of 2%');
    }

    public function test_signed_in_agent_sees_their_own_link(): void
    {
        $agent = Agent::create(['name' => 'Aina', 'email' => 'aina@example.com', 'phone' => '0123456789', 'location' => 'KL', 'requested_code' => 'aina', 'password' => 'test-password-1234', 'terms_accepted_at' => now()]);
        $source = ReferralSource::create(['name' => 'Aina', 'code' => 'aina', 'channel' => 'agent', 'is_active' => true]);
        $agent->forceFill(['status' => 'approved', 'email_verified_at' => now(), 'referral_source_id' => $source->id])->save();

        $this->actingAs($agent->fresh(), 'agent')->get(route('agents.how-it-works'))->assertOk()
            ->assertSee($source->tracking_url)
            ->assertSee('Go to my dashboard');
    }
}
