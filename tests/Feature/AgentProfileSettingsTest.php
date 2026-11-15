<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\ReferralSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgentProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = Agent::create(['name' => 'Aina', 'email' => 'aina@example.com', 'phone' => '0123456789', 'location' => 'KL', 'requested_code' => 'aina', 'password' => 'test-password-1234', 'terms_accepted_at' => now()]);
        $source = ReferralSource::create(['name' => 'Aina', 'code' => 'aina', 'channel' => 'agent', 'is_active' => true]);
        $this->agent->forceFill(['status' => 'approved', 'email_verified_at' => now(), 'referral_source_id' => $source->id, 'social_links' => ['instagram' => 'https://instagram.com/old.handle']])->save();
    }

    private function save(array $overrides = [])
    {
        return $this->actingAs($this->agent, 'agent')->patch(route('agents.account.update'), [
            'name' => 'Aina Binti Ali', 'phone_local' => '12-345 6789', 'location' => 'Shah Alam',
            'social_links' => [
                'website' => 'https://aina.my', 'instagram' => '@bell.naza', 'tiktok' => 'https://www.tiktok.com/@aina.clean?lang=en',
                'threads' => '', 'x' => 'x.com/aina_my', 'youtube' => 'AinaChannel', 'facebook' => 'https://www.facebook.com/aina.page/',
            ],
            ...$overrides,
        ]);
    }

    public function test_handles_and_pasted_links_are_stored_as_clean_urls(): void
    {
        $this->save()->assertSessionHasNoErrors();

        $agent = $this->agent->fresh();
        $this->assertSame('+60123456789', $agent->phone);
        $this->assertSame([
            'website' => 'https://aina.my',
            'instagram' => 'https://instagram.com/bell.naza',
            'tiktok' => 'https://www.tiktok.com/@aina.clean',
            'x' => 'https://x.com/aina_my',
            'youtube' => 'https://www.youtube.com/@AinaChannel',
            'facebook' => 'https://facebook.com/aina.page',
        ], $agent->social_links);
        $this->assertSame('bell.naza', $agent->socialHandle('instagram'));
        $this->assertSame('aina.my', $agent->socialHandle('website'));
        $this->assertSame('123456789', $agent->phoneLocal());
    }

    public function test_phone_accepts_plus_60_or_leading_zero_and_rejects_junk(): void
    {
        $this->save(['phone_local' => '+60 12-345 6789'])->assertSessionHasNoErrors();
        $this->assertSame('+60123456789', $this->agent->fresh()->phone);
        $this->save(['phone_local' => '012 345 6789'])->assertSessionHasNoErrors();
        $this->assertSame('+60123456789', $this->agent->fresh()->phone);
        $this->save(['phone_local' => '123'])->assertSessionHasErrors('phone_local');
    }

    public function test_bad_handle_is_rejected(): void
    {
        $this->save(['social_links' => ['instagram' => 'not a handle!']])->assertSessionHasErrors('social_links.instagram');
    }

    public function test_page_shows_prefixes_and_existing_handles(): void
    {
        $this->actingAs($this->agent, 'agent')->get(route('agents.account'))->assertOk()
            ->assertSee('PROFILE PHOTO')->assertSee('youtube.com/@')->assertSee('fb.com/')->assertSee('https://')
            ->assertSee('value="old.handle"', false)->assertSee('value="123456789"', false);
    }

    public function test_photo_upload_replace_delete_and_privacy(): void
    {
        Storage::fake('local');
        $this->actingAs($this->agent, 'agent')->post(route('agents.account.photo.update'), ['photo' => UploadedFile::fake()->image('me.jpg', 400, 400)])->assertSessionHasNoErrors();
        $first = $this->agent->fresh()->profile_photo_path;
        Storage::disk('local')->assertExists($first);
        $this->get(route('agents.account.photo'))->assertOk();

        $this->post(route('agents.account.photo.update'), ['photo' => UploadedFile::fake()->image('new.png', 300, 300)])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($first);

        $this->post(route('agents.account.photo.update'), ['photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])->assertSessionHasErrorsIn('photo', 'photo');
        $this->post(route('agents.account.photo.update'), ['photo' => UploadedFile::fake()->image('tiny.png', 20, 20)])->assertSessionHasErrorsIn('photo', 'photo');

        // actingAs(..., 'agent') switched the default guard, so name the admin guard explicitly.
        $this->actingAs(User::factory()->create(), 'web')->get(route('admin.agents.photo', $this->agent))->assertOk();

        $this->actingAs($this->agent->fresh(), 'agent')->delete(route('agents.account.photo.delete'))->assertSessionHasNoErrors();
        $this->assertNull($this->agent->fresh()->profile_photo_path);
        $this->get(route('agents.account.photo'))->assertNotFound();
    }
}
