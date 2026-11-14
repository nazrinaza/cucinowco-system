<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\ReferralSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentCodeAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function check(string $code)
    {
        return $this->getJson(route('agents.register.check-code', ['code' => $code]));
    }

    public function test_free_code_is_available_and_input_is_normalised(): void
    {
        $this->check('  Ahmad-KL ')->assertOk()->assertJson(['code' => 'ahmad-kl', 'available' => true]);
    }

    public function test_code_requested_by_another_applicant_is_taken(): void
    {
        Agent::create(['name' => 'Aina', 'email' => 'aina@example.com', 'phone' => '0123456789', 'location' => 'KL', 'requested_code' => 'aina', 'password' => 'test-password-1234', 'terms_accepted_at' => now()]);

        $this->check('AINA')->assertOk()->assertJson(['available' => false, 'message' => 'This code is already taken. Please choose another.']);
    }

    public function test_code_used_by_an_existing_referral_source_is_taken(): void
    {
        ReferralSource::create(['name' => 'Promo', 'code' => 'raya2026', 'channel' => 'campaign', 'is_active' => true]);

        $this->check('raya2026')->assertJson(['available' => false]);
    }

    public function test_reserved_and_malformed_codes_are_rejected(): void
    {
        $this->check('admin')->assertJson(['available' => false, 'message' => 'This code is reserved. Please choose another.']);
        $this->check('ab')->assertJson(['available' => false]);
        $this->check('-ahmad')->assertJson(['available' => false]);
        $this->check('ahmad lee')->assertJson(['available' => false]);
    }

    public function test_check_is_rate_limited(): void
    {
        foreach (range(1, 30) as $i) {
            $this->check('code'.$i)->assertOk();
        }
        $this->check('code31')->assertStatus(429);
    }

    public function test_registration_form_wires_up_the_live_check(): void
    {
        $this->get(route('agents.register'))->assertOk()
            ->assertSee('data-code-check="'.route('agents.register.check-code').'"', false)
            ->assertSee('id="code-check-status"', false);
    }
}
