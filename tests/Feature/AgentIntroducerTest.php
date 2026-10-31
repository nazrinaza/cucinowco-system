<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\ReferralSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AgentIntroducerTest extends TestCase
{
    use RefreshDatabase;

    private function apply(array $extra = []): Agent
    {
        Mail::fake();
        $this->post(route('agents.register.store'), array_merge([
            'name' => 'New Agent', 'email' => 'new@example.com', 'phone' => '601112345678',
            'location' => 'Selangor', 'requested_code' => 'new-agent', 'password' => 'password-12345',
            'password_confirmation' => 'password-12345', 'terms' => '1',
        ], $extra))->assertSessionHasNoErrors()->assertRedirect(route('agents.account'));

        return Agent::where('email', 'new@example.com')->firstOrFail();
    }

    private function source(): ReferralSource
    {
        return ReferralSource::create(['name' => 'Azim Partner', 'code' => 'azim', 'channel' => 'partner', 'is_active' => true]);
    }

    public function test_root_invitation_survives_redirect_and_registration_navigation(): void
    {
        $source = $this->source();
        $this->get(route('agents.dashboard', ['ref' => 'azim']))->assertRedirect(route('agents.login'));
        $this->get(route('agents.login'))->assertOk();
        $this->get(route('agents.register'))->assertOk();
        $agent = $this->apply();
        $this->assertSame($source->id, $agent->introducer_source_id);
        $this->assertSame('azim', $agent->introducer_code);
        $this->assertSame('partner', $agent->introducer_channel);
        $this->assertSame(0, $source->fresh()->visit_count);
        $this->assertDatabaseCount('referral_visits', 0);
        $this->actingAs(User::factory()->create(), 'web')->get(route('admin.agents.index'))->assertOk()->assertSee('Azim Partner');
        $this->get(route('admin.agents.show', $agent))->assertOk()->assertSee('Introduced by')->assertSee('Azim Partner');
        $source->delete();
        $this->assertSame('azim', $agent->fresh()->introducer_code);
        $this->assertNull($agent->fresh()->introducer_source_id);
    }

    public function test_direct_registration_link_saves_introducer(): void
    {
        $source = $this->source();
        $this->get(route('agents.register', ['ref' => 'azim']))->assertOk();
        $agent = $this->apply();
        $this->assertSame($source->id, $agent->introducer_source_id);
        $this->assertSame('azim', $agent->introducer_code);
        $this->assertSame('Azim Partner', $agent->introducer_name);
    }

    public function test_unknown_or_forged_introducer_is_direct(): void
    {
        $this->source();
        $this->get(route('agents.register', ['ref' => 'unknown']))->assertOk();
        $agent = $this->apply(['introducer_source_id' => 1, 'introducer_code' => 'azim']);
        $this->assertSame('direct', $agent->introducer_code);
        $this->assertNull($agent->introducer_source_id);
    }

    public function test_cookie_restores_attribution_without_customer_referral_session(): void
    {
        $this->source();
        $this->withCookie('cucinow_agent_introducer', json_encode(['code' => 'azim', 'expires_at' => now()->addDay()->timestamp]))
            ->get(route('agents.register'))->assertOk();
        $this->assertSame('azim', $this->apply()->introducer_code);
    }

    public function test_expired_attribution_is_direct(): void
    {
        $this->source();
        $this->withSession(['agent_introducer' => ['code' => 'azim', 'expires_at' => now()->subMinute()->timestamp]]);
        $this->assertSame('direct', $this->apply()->introducer_code);
    }

    public function test_source_disabled_after_landing_cannot_claim_application(): void
    {
        $source = $this->source();
        $this->get(route('agents.register', ['ref' => 'azim']));
        $source->update(['is_active' => false]);
        $this->assertSame('direct', $this->apply()->introducer_code);
    }

    public function test_approved_agent_can_introduce_and_suspension_blocks_attribution(): void
    {
        $source = $this->source();
        $source->update(['channel' => 'agent']);
        $owner = Agent::create(['name' => 'Azim', 'email' => 'azim@example.com', 'phone' => '601112345678', 'location' => 'KL', 'requested_code' => 'azim', 'password' => 'password-12345', 'terms_accepted_at' => now()]);
        $owner->forceFill(['referral_source_id' => $source->id, 'status' => 'approved', 'email_verified_at' => now()])->save();
        $this->get(route('agents.register', ['ref' => 'azim']))->assertSessionHas('agent_introducer.code', 'azim');
        $owner->forceFill(['status' => 'suspended'])->save();
        $this->assertSame('direct', $this->apply()->introducer_code);
    }
}
