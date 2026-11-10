<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppEnquiryAcknowledgement;
use App\Livewire\WhatsAppEnquiryForm;
use App\Models\User;
use App\Models\WhatsAppEnquiry;
use App\Services\MetaWhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_form_saves_consent_and_queues_acknowledgement(): void
    {
        Queue::fake();

        Livewire::test(WhatsAppEnquiryForm::class)
            ->set('name', 'Aina Rahman')
            ->set('phone', '60123456789')
            ->set('email', 'aina@example.com')
            ->set('message', 'Please advise about carpet cleaning.')
            ->set('consent', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $enquiry = WhatsAppEnquiry::firstOrFail();
        $this->assertTrue($enquiry->whatsapp_consent);
        $this->assertSame('Aina Rahman', $enquiry->name);
        Queue::assertPushed(SendWhatsAppEnquiryAcknowledgement::class, fn ($job) => $job->enquiry->is($enquiry));
    }

    public function test_consent_is_required(): void
    {
        Livewire::test(WhatsAppEnquiryForm::class)
            ->set('name', 'Aina Rahman')
            ->set('phone', '60123456789')
            ->set('message', 'Please call me.')
            ->call('submit')
            ->assertHasErrors(['consent']);

        $this->assertDatabaseCount('whatsapp_enquiries', 0);
    }

    public function test_queue_job_calls_meta_without_blocking_form_request(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.api_version' => 'v99.0',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123456',
            'services.whatsapp.template.name' => 'site_visit_enquiry_received',
            'services.whatsapp.template.language' => 'en',
        ]);
        Http::fake(['https://graph.facebook.com/v99.0/123456/messages' => Http::response(['messages' => [['id' => 'wamid.123']]], 200)]);
        $enquiry = WhatsAppEnquiry::create([
            'reference_number' => 'WA-TEST-1', 'name' => 'Aina', 'phone' => '60123456789',
            'message' => 'Hello', 'whatsapp_consent' => true,
        ]);

        (new SendWhatsAppEnquiryAcknowledgement($enquiry))->handle(app(MetaWhatsAppClient::class));

        $this->assertSame('wamid.123', $enquiry->fresh()->provider_message_id);
        Http::assertSent(fn ($request) => $request['template']['components'][0]['parameters'][1]['text'] === 'WA-TEST-1');
    }

    public function test_webhook_verification_requires_matching_token(): void
    {
        config(['services.whatsapp.webhook_verify_token' => 'verify-secret']);

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=123')->assertForbidden();
        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=verify-secret&hub_challenge=123')
            ->assertOk()->assertSeeText('123');
    }

    public function test_signed_webhook_updates_delivery_status(): void
    {
        config(['services.whatsapp.app_secret' => 'app-secret']);
        $enquiry = WhatsAppEnquiry::create([
            'reference_number' => 'WA-TEST-2', 'name' => 'Aina', 'phone' => '60123456789',
            'message' => 'Hello', 'whatsapp_consent' => true, 'provider_message_id' => 'wamid.456',
        ]);
        $payload = json_encode(['entry' => [[
            'changes' => [['value' => ['statuses' => [[
                'id' => 'wamid.456', 'status' => 'delivered', 'timestamp' => '1794355200',
            ]]]]],
        ]]]) ?: '';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'app-secret');

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $payload)->assertOk();

        $this->assertSame('delivered', $enquiry->fresh()->provider_status);
        $this->assertNotNull($enquiry->fresh()->delivered_at);
    }

    public function test_unsigned_webhook_is_rejected(): void
    {
        config(['services.whatsapp.app_secret' => 'app-secret']);
        $this->postJson('/webhooks/whatsapp', ['entry' => []])->assertForbidden();
    }

    public function test_admin_list_requires_authentication(): void
    {
        $this->get('/admin/whatsapp-enquiries')->assertRedirect('/admin/login');

        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($user)->get('/admin/whatsapp-enquiries')->assertOk();
    }
}
