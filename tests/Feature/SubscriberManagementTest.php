<?php

namespace Tests\Feature;

use App\Jobs\SendNewsletterCampaign;
use App\Mail\NewsletterMail;
use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manually_add_and_delete_a_subscriber(): void
    {
        $admin = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->post(route('admin.subscribers.store'), [
            'name' => '  Aina Rahman  ',
            'email' => 'AINA@example.com',
            'phone' => '  +60 11-1234 5678  ',
            'status' => 'subscribed',
            'segment' => 'Corporate Clients',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $subscriber = Subscriber::where('email', 'aina@example.com')->firstOrFail();
        $this->assertSame('Aina Rahman', $subscriber->name);
        $this->assertSame('+60 11-1234 5678', $subscriber->phone);
        $this->assertSame('manual', $subscriber->source);
        $this->assertSame(['Corporate Clients'], $subscriber->segments);

        $this->actingAs($admin)
            ->delete(route('admin.subscribers.destroy', $subscriber))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('subscribers', ['id' => $subscriber->id]);
    }

    public function test_manual_subscriber_email_must_be_unique(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        Subscriber::create(['email' => 'existing@example.com', 'status' => 'subscribed', 'subscribed_at' => now()]);

        $this->actingAs($admin)->post(route('admin.subscribers.store'), [
            'email' => 'EXISTING@example.com',
            'status' => 'subscribed',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, Subscriber::where('email', 'existing@example.com')->count());
    }

    public function test_admin_can_import_subscribers_and_merge_segments(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        Subscriber::create([
            'email' => 'existing@example.com',
            'name' => 'Old Name',
            'status' => 'subscribed',
            'segments' => ['Existing'],
            'subscribed_at' => now(),
        ]);
        $csv = <<<'CSV'
email,name,phone,status,segments
aina@example.com,Aina Rahman,60111234567,subscribed,Corporate|VIP
existing@example.com,Updated Name,60119876543,subscribed,VIP
not-an-email,Broken Row,,,VIP
CSV;

        $this->actingAs($admin)->post(route('admin.subscribers.import'), [
            'subscribers_csv' => UploadedFile::fake()->createWithContent('subscribers.csv', $csv),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $aina = Subscriber::where('email', 'aina@example.com')->firstOrFail();
        $existing = Subscriber::where('email', 'existing@example.com')->firstOrFail();

        $this->assertSame('Aina Rahman', $aina->name);
        $this->assertSame(['Corporate', 'VIP'], $aina->segments);
        $this->assertSame('csv_import', $aina->source);
        $this->assertSame('Updated Name', $existing->name);
        $this->assertSame(['Existing', 'VIP'], $existing->segments);
        $this->assertDatabaseMissing('subscribers', ['email' => 'not-an-email']);
    }

    public function test_admin_can_export_the_filtered_segment_as_csv(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        Subscriber::create(['email' => 'vip@example.com', 'status' => 'subscribed', 'segments' => ['VIP'], 'subscribed_at' => now()]);
        Subscriber::create(['email' => 'general@example.com', 'status' => 'subscribed', 'segments' => ['General'], 'subscribed_at' => now()]);

        $response = $this->actingAs($admin)->get(route('admin.subscribers.export', ['segment' => 'VIP']));
        $content = $response->streamedContent();

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('vip@example.com', $content);
        $this->assertStringNotContainsString('general@example.com', $content);
    }

    public function test_admin_can_add_and_remove_a_segment_in_bulk(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $first = Subscriber::create(['email' => 'one@example.com', 'status' => 'subscribed', 'segments' => [], 'subscribed_at' => now()]);
        $second = Subscriber::create(['email' => 'two@example.com', 'status' => 'subscribed', 'segments' => ['Existing'], 'subscribed_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.subscribers.segments'), [
            'subscriber_ids' => [$first->id, $second->id],
            'segment_action' => 'add',
            'segment' => 'Corporate Clients',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(['Corporate Clients'], $first->refresh()->segments);
        $this->assertSame(['Corporate Clients', 'Existing'], $second->refresh()->segments);

        $this->actingAs($admin)->patch(route('admin.subscribers.segments'), [
            'subscriber_ids' => [$first->id, $second->id],
            'segment_action' => 'remove',
            'segment' => 'Corporate Clients',
        ])->assertSessionHasNoErrors();

        $this->assertSame([], $first->refresh()->segments);
        $this->assertSame(['Existing'], $second->refresh()->segments);
    }

    public function test_segmented_campaign_only_queues_matching_subscribers_once(): void
    {
        Mail::fake();
        $vip = Subscriber::create(['email' => 'vip@example.com', 'status' => 'subscribed', 'segments' => ['VIP', 'Corporate'], 'subscribed_at' => now()]);
        Subscriber::create(['email' => 'general@example.com', 'status' => 'subscribed', 'segments' => ['General'], 'subscribed_at' => now()]);
        Subscriber::create(['email' => 'off@example.com', 'status' => 'unsubscribed', 'segments' => ['VIP'], 'subscribed_at' => now()]);
        $campaign = NewsletterCampaign::create([
            'name' => 'VIP campaign',
            'subject' => 'For VIP subscribers',
            'content' => '<p>Segmented content</p>',
            'segments' => ['VIP', 'Corporate'],
            'status' => 'queued',
        ]);

        (new SendNewsletterCampaign($campaign->id))->handle();

        Mail::assertQueued(NewsletterMail::class, 1);
        Mail::assertQueued(NewsletterMail::class, fn (NewsletterMail $mail): bool => $mail->subscriber->is($vip));
        $this->assertSame(1, $campaign->refresh()->recipient_count);
    }
}
