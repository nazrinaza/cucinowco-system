<?php

namespace Tests\Feature;

use App\Livewire\SiteVisitForm;
use App\Mail\AgentAccessMail;
use App\Models\Agent;
use App\Models\AgentMedia;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\ReferralSource;
use App\Models\ReferralVisit;
use App\Models\SiteVisitRequest;
use App\Models\User;
use App\Support\AgentReporting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class AgentPortalTest extends TestCase
{
    use RefreshDatabase;

    private function agent(string $code = 'ahmad', string $status = 'approved'): Agent
    {
        $agent = Agent::create(['name' => ucfirst($code), 'email' => $code.'@example.com', 'phone' => '601112345678', 'location' => 'Selangor', 'requested_code' => $code, 'password' => 'test-password-1234', 'terms_accepted_at' => now()]);
        $agent->forceFill(['status' => $status, 'email_verified_at' => now()])->save();
        if ($status === 'approved') {
            $source = ReferralSource::create(['name' => $agent->name, 'code' => $code, 'channel' => 'agent', 'is_active' => true]);
            $agent->forceFill(['referral_source_id' => $source->id])->save();
        }

        return $agent;
    }

    public function test_registration_requires_verification_and_hq_approval_and_cannot_inject_privileges(): void
    {
        Mail::fake();
        $this->post(route('agents.register.store'), [
            'name' => 'Aina', 'email' => 'AINA@example.com', 'phone' => '601112345678', 'location' => 'Selangor', 'requested_code' => 'aina', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123', 'terms' => '1',
            'status' => 'approved', 'referral_source_id' => 123,
        ])->assertRedirect(route('agents.account'));
        $agent = Agent::firstOrFail();
        $this->assertSame('aina@example.com', $agent->email);
        $this->assertSame('pending', $agent->status);
        $this->assertNull($agent->referral_source_id);
        $this->assertNull($agent->email_verified_at);
        $this->assertTrue(Hash::check('new-password-123', $agent->password));
        $this->assertDatabaseCount('users', 0);
        $this->get(route('agents.dashboard'))->assertRedirect(route('agents.account'));
        Mail::assertQueued(AgentAccessMail::class, fn ($mail) => $mail->hasTo('aina@example.com'));

        $url = URL::temporarySignedRoute('agents.verify', now()->addHour(), ['agent' => $agent->id, 'hash' => sha1($agent->email)]);
        $this->get($url)->assertRedirect(route('agents.login'));
        $this->assertNotNull($agent->fresh()->email_verified_at);
        $this->assertSame('pending', $agent->fresh()->status);
        $this->get(str_replace(sha1($agent->email), str_repeat('a', 40), $url))->assertForbidden();
    }

    public function test_hq_can_approve_with_existing_qr_and_suspension_blocks_existing_sessions(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $agent = $this->agent(status: 'pending');
        $source = ReferralSource::create(['name' => 'Existing Ahmad QR', 'code' => 'ahmad-old', 'channel' => 'agent', 'is_active' => true, 'visit_count' => 12]);
        $this->actingAs($admin)->patch(route('admin.agents.update', $agent), ['status' => 'approved', 'referral_source_id' => $source->id])->assertSessionHasNoErrors();
        $this->assertSame($source->id, $agent->fresh()->referral_source_id);
        $this->assertSame(12, $source->fresh()->visit_count);
        $this->actingAs($agent->fresh(), 'agent')->get(route('agents.dashboard'))->assertOk()->assertSee('ahmad-old');
        $this->actingAs($admin, 'web')->patch(route('admin.agents.update', $agent), ['status' => 'suspended'])->assertSessionHasNoErrors();
        $this->assertFalse($source->fresh()->is_active);
        $this->get(route('agents.dashboard'))->assertRedirect(route('agents.account'));
        $this->actingAs($admin, 'web')->patch(route('admin.referrals.update', $source), ['is_active' => 1])->assertSessionHasErrors('is_active');
    }

    public function test_unverified_agents_cannot_be_approved_and_sources_cannot_be_stolen(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $owner = $this->agent('owner');
        $applicant = $this->agent('applicant', 'pending');
        $applicant->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($admin)->patch(route('admin.agents.update', $applicant), ['status' => 'approved'])->assertSessionHasErrors('status');
        $applicant->forceFill(['email_verified_at' => now()])->save();
        $this->patch(route('admin.agents.update', $applicant), ['status' => 'approved', 'referral_source_id' => $owner->referral_source_id])->assertSessionHasErrors('referral_source_id');
        $this->assertNull($applicant->fresh()->referral_source_id);
    }

    public function test_agent_and_staff_guards_are_separate(): void
    {
        $agent = $this->agent();
        $this->post(route('agents.login.store'), ['email' => $agent->email, 'password' => 'test-password-1234'])->assertRedirect(route('agents.dashboard'));
        $this->assertAuthenticatedAs($agent, 'agent');
        $this->assertGuest('web');
        foreach (['admin.dashboard', 'admin.agents.index', 'admin.site-visits.index', 'admin.agent-media.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->create(['role' => 'field']), 'web')->get(route('admin.agents.index'))->assertForbidden();
    }

    public function test_guest_and_hq_portal_pages_render(): void
    {
        $this->get(route('agents.register'))->assertOk()->assertSee('Good connections. Cleaner spaces.');
        $this->get(route('agents.login'))->assertOk()->assertSee('Welcome back, partner.');
        $this->get(route('agents.guide'))->assertOk()->assertSee('Attribution and fair sharing');

        $admin = User::factory()->create();
        $agent = $this->agent();
        $this->actingAs($admin, 'web')->get(route('admin.agents.index'))->assertOk()->assertSee($agent->name);
        $this->get(route('admin.agents.show', $agent))->assertOk()->assertSeeText('Review & access');
        $this->get(route('admin.agent-media.index'))->assertOk()->assertSee('Publish marketing content');
    }

    public function test_qr_and_link_visits_are_deduplicated_and_source_is_saved_on_lead(): void
    {
        Mail::fake();
        $agent = $this->agent();
        $this->withHeader('User-Agent', 'Mozilla/5.0 iPhone Mobile Safari')->get('/?ref=ahmad&via=qr')->assertOk();
        $this->get('/?ref=ahmad&via=qr')->assertOk();
        $this->assertSame(1, $agent->referralSource->fresh()->visit_count);
        $this->assertDatabaseCount('referral_visits', 1);
        $this->assertDatabaseHas('referral_visits', ['channel' => 'qr', 'device' => 'mobile']);
        Livewire::test(SiteVisitForm::class)->set('cleanTypes', ['general_cleaning'])->set('spaceType', 'office')->set('name', 'Private Customer')->set('phone', '01112345678')->set('email', 'private@example.com')->set('preferredDate', now()->addDay()->format('Y-m-d'))->set('preferredTimeSlot', 'morning')->call('submit')->assertHasNoErrors();
        $lead = SiteVisitRequest::firstOrFail();
        $this->assertSame($agent->referral_source_id, $lead->referral_source_id);
        $this->actingAs($agent, 'agent')->get(route('agents.leads'))->assertOk()->assertSee($lead->reference_number)->assertDontSee('private@example.com')->assertDontSee('Private Customer')->assertDontSee('01112345678');
    }

    public function test_bot_and_authenticated_previews_do_not_inflate_visits(): void
    {
        $agent = $this->agent();
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get('/?ref=ahmad');
        $this->assertDatabaseCount('referral_visits', 0);
        $this->actingAs($agent, 'agent')->withHeader('User-Agent', 'Mozilla/5.0')->get('/?ref=ahmad');
        $this->assertDatabaseCount('referral_visits', 0);
    }

    public function test_dashboard_reports_only_own_distinct_closed_deals_and_analytics(): void
    {
        $agent = $this->agent();
        $other = $this->agent('another');
        $customer = Customer::create(['name' => 'Confidential Customer', 'phone' => '0123456789']);
        $quote = Quote::create(['quote_number' => 'Q-AGENT-1', 'customer_id' => $customer->id, 'status' => 'accepted']);
        Booking::create(['booking_number' => 'B-AGENT-1', 'customer_id' => $customer->id, 'quote_id' => $quote->id, 'status' => 'completed', 'service_address' => 'Private Site', 'scheduled_start' => now()]);
        Invoice::create(['invoice_number' => 'I-AGENT-1', 'customer_id' => $customer->id, 'quote_id' => $quote->id, 'status' => 'paid', 'balance' => 0, 'issued_at' => today(), 'due_at' => today()]);
        foreach ([$agent, $other] as $index => $owner) {
            SiteVisitRequest::create(['reference_number' => 'SV-AGENT-'.$index, 'customer_id' => $customer->id, 'quote_id' => $index === 0 ? $quote->id : null, 'referral_source_id' => $owner->referral_source_id, 'referral_code' => $owner->requested_code, 'space_type' => 'office', 'status' => 'new', 'site_address' => 'Private Site']);
            ReferralVisit::create(['referral_source_id' => $owner->referral_source_id, 'session_hash' => hash('sha256', $owner->id), 'channel' => 'link', 'landing_path' => $index ? '/hidden-other-agent' : '/', 'device' => 'mobile', 'created_at' => now()]);
        }
        $summary = app(AgentReporting::class)->summary($agent);
        $this->assertSame(1, $summary['closed']);
        $this->assertSame(1, $summary['completed']);
        $this->assertSame(1, $summary['paid']);
        $this->actingAs($agent, 'agent')->get(route('agents.leads'))->assertOk()->assertSee('SV-AGENT-0')->assertDontSee('SV-AGENT-1')->assertSee('Paid');
        $this->get(route('agents.analytics'))->assertOk()->assertDontSee('/hidden-other-agent')->assertSee('1 visits');
        $this->get(route('agents.dashboard'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_hq_content_is_private_and_unpublished_or_expired_files_cannot_be_downloaded(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $agent = $this->agent();
        $this->actingAs($admin)->post(route('admin.agent-media.store'), ['title' => 'Office poster', 'category' => 'poster', 'caption' => 'Book now: {ReferralLink}', 'is_published' => 1, 'file' => UploadedFile::fake()->image('poster.png')])->assertSessionHasNoErrors();
        $media = AgentMedia::firstOrFail();
        Storage::disk('local')->assertExists($media->path);
        $this->actingAs($agent, 'agent')->get(route('agents.media'))->assertOk()->assertSee('?ref=ahmad')->assertDontSee('{ReferralLink}');
        $this->get(route('agents.media.download', $media))->assertOk();
        $this->get(route('agents.media.preview', $media))->assertOk();
        $media->update(['is_published' => false]);
        $this->get(route('agents.media.download', $media))->assertNotFound();
        $media->update(['is_published' => true, 'expires_at' => now()->subMinute()]);
        $this->get(route('agents.media.preview', $media))->assertNotFound();
        Auth::guard('web')->logout();
        $this->post(route('admin.agent-media.store'), ['title' => 'Unauthorised'])->assertRedirect(route('login'));
        Auth::guard('agent')->logout();
        $this->get(route('agents.media.download', $media))->assertRedirect(route('agents.login'));
    }

    public function test_agent_password_reset_does_not_reset_staff_password(): void
    {
        Mail::fake();
        $agent = $this->agent();
        $staff = User::factory()->create(['email' => $agent->email, 'password' => 'staff-password-123']);
        $token = Password::broker('agents')->createToken($agent);
        $this->post(route('agents.password.update'), ['email' => $agent->email, 'token' => $token, 'password' => 'changed-agent-123', 'password_confirmation' => 'changed-agent-123'])->assertRedirect(route('agents.login'));
        $this->assertTrue(Hash::check('changed-agent-123', $agent->fresh()->password));
        $this->assertTrue(Hash::check('staff-password-123', $staff->fresh()->password));
        $this->post(route('agents.password.update'), ['email' => $agent->email, 'token' => $token, 'password' => 'reused-token-123', 'password_confirmation' => 'reused-token-123'])->assertSessionHasErrors('email');
    }
}
