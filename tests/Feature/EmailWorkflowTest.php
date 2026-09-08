<?php

namespace Tests\Feature;

use App\Jobs\SendNewsletterCampaign;
use App\Listeners\HandleResendEmailEvent;
use App\Livewire\SiteVisitForm;
use App\Mail\BookingConfirmationMail;
use App\Mail\InvoiceMail;
use App\Mail\InvoiceOverdueReminderMail;
use App\Mail\NewSiteVisitNotificationMail;
use App\Mail\NewsletterMail;
use App\Mail\NewsletterPreviewMail;
use App\Mail\PaymentReceiptMail;
use App\Mail\QuoteMail;
use App\Mail\SiteVisitConfirmationMail;
use App\Mail\SubscriberWelcomeMail;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\EmailEvent;
use App\Models\Invoice;
use App\Models\NewsletterCampaign;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Service;
use App\Models\SiteVisitRequest;
use App\Models\Subscriber;
use App\Models\User;
use App\Support\NewsletterHtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Resend\Laravel\Events\EmailOpened;
use Tests\TestCase;

class EmailWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_visit_submission_queues_customer_and_team_emails(): void
    {
        Mail::fake();
        $service = Service::create(['code' => 'office-cleaning', 'name' => 'Office Cleaning', 'unit' => 'job', 'is_active' => true]);

        Livewire::test(SiteVisitForm::class)
            ->set('serviceId', $service->id)
            ->set('spaceType', 'office')
            ->set('name', 'Aina Rahman')
            ->set('phone', '0123456789')
            ->set('email', 'aina@example.com')
            ->set('preferredDate', now()->addDay()->format('Y-m-d'))
            ->set('preferredTimeSlot', 'morning')
            ->set('siteAddress', 'Example Tower, Shah Alam')
            ->set('postcode', '40160')
            ->call('submit')
            ->assertHasNoErrors();

        Mail::assertQueued(SiteVisitConfirmationMail::class, fn ($mail) => $mail->hasTo('aina@example.com'));
        Mail::assertQueued(NewSiteVisitNotificationMail::class, fn ($mail) => $mail->hasTo(config('company.notifications_email')));
    }

    public function test_admin_can_queue_transactional_customer_emails(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_active' => true]);
        $customer = Customer::create(['name' => 'Example Customer', 'phone' => '0111111111', 'email' => 'customer@example.com']);
        $quote = Quote::create(['quote_number' => 'Q-EMAIL-1', 'customer_id' => $customer->id, 'status' => 'draft', 'subtotal' => 250, 'total' => 250, 'valid_until' => now()->addDays(14), 'service_address' => 'Example Tower']);
        $quote->items()->create(['description' => 'Office Cleaning', 'quantity' => 1, 'unit' => 'job', 'unit_price' => 250, 'amount' => 250]);
        $invoice = Invoice::create(['invoice_number' => 'INV-EMAIL-1', 'customer_id' => $customer->id, 'quote_id' => $quote->id, 'status' => 'draft', 'issued_at' => today(), 'due_at' => today()->addDays(14), 'subtotal' => 250, 'total' => 250, 'balance' => 250]);
        $invoice->items()->create(['description' => 'Office Cleaning', 'quantity' => 1, 'unit' => 'job', 'unit_price' => 250, 'amount' => 250]);

        $this->actingAs($admin)->post(route('admin.quotes.send', $quote))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.invoices.send', $invoice))->assertSessionHas('success');
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'sent']);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'sent']);
        $this->actingAs($admin)->post(route('admin.quotes.book', $quote), ['scheduled_start' => now()->addDay()->format('Y-m-d H:i:s')])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.invoices.payments', $invoice), ['amount' => 100, 'method' => 'fpx', 'paid_at' => now()->format('Y-m-d H:i:s'), 'reference' => 'TEST-PAYMENT'])->assertSessionHas('success');

        Mail::assertQueued(QuoteMail::class, fn ($mail) => $mail->hasTo('customer@example.com'));
        Mail::assertQueued(InvoiceMail::class, fn ($mail) => $mail->hasTo('customer@example.com'));
        Mail::assertQueued(BookingConfirmationMail::class, fn ($mail) => $mail->hasTo('customer@example.com'));
        Mail::assertQueued(PaymentReceiptMail::class, fn ($mail) => $mail->hasTo('customer@example.com'));
    }

    public function test_subscriber_receives_welcome_email_and_can_unsubscribe(): void
    {
        Mail::fake();

        $this->post(route('newsletter.store'), ['email' => 'reader@example.com'])->assertSessionHas('newsletter_success');
        $subscriber = Subscriber::where('email', 'reader@example.com')->firstOrFail();

        Mail::assertQueued(SubscriberWelcomeMail::class, fn ($mail) => $mail->hasTo('reader@example.com'));

        $url = URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $subscriber->id]);
        $this->get($url)->assertOk()->assertSee('You have been unsubscribed');
        $this->assertDatabaseHas('subscribers', ['id' => $subscriber->id, 'status' => 'unsubscribed']);
    }

    public function test_campaign_dispatch_queues_one_email_per_active_subscriber(): void
    {
        Mail::fake();
        Subscriber::create(['email' => 'one@example.com', 'status' => 'subscribed', 'subscribed_at' => now()]);
        Subscriber::create(['email' => 'two@example.com', 'status' => 'subscribed', 'subscribed_at' => now()]);
        Subscriber::create(['email' => 'off@example.com', 'status' => 'unsubscribed', 'subscribed_at' => now()]);
        $campaign = NewsletterCampaign::create(['name' => 'Test', 'subject' => 'A cleaner workspace', 'content' => 'Campaign content', 'status' => 'queued']);

        (new SendNewsletterCampaign($campaign->id))->handle();

        Mail::assertQueued(NewsletterMail::class, 2);
        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(2, $campaign->recipient_count);
    }

    public function test_admin_send_now_queues_campaign_dispatch_job(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['is_active' => true]);
        $campaign = NewsletterCampaign::create(['name' => 'Test', 'subject' => 'Subject', 'content' => 'Content', 'status' => 'draft']);

        $this->actingAs($admin)->post(route('admin.campaigns.send', $campaign))->assertSessionHas('success');

        Queue::assertPushed(SendNewsletterCampaign::class, fn ($job) => $job->campaignId === $campaign->id);
        $this->assertDatabaseHas('newsletter_campaigns', ['id' => $campaign->id, 'status' => 'queued']);
    }

    public function test_admin_can_build_a_sanitized_html_newsletter(): void
    {
        $admin = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.campaigns.index'))
            ->assertOk()
            ->assertSee('data-html-editor', false)
            ->assertSee('data-editor-image-button', false)
            ->assertSee('&lt;/&gt; HTML', false);

        $this->actingAs($admin)->post(route('admin.campaigns.store'), [
            'name' => 'HTML campaign',
            'subject' => 'A cleaner workplace',
            'preview_text' => 'Professional cleaning notes',
            'content' => '<h2>Welcome</h2><p onclick="alert(1)">A <strong>cleaner</strong> workplace.</p><a href="https://cucinow.co">Book now</a><a href="javascript:alert(1)">Unsafe</a><script>alert(1)</script>',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $campaign = NewsletterCampaign::where('name', 'HTML campaign')->firstOrFail();

        $this->assertStringContainsString('<h2', $campaign->content);
        $this->assertStringContainsString('<strong>cleaner</strong>', $campaign->content);
        $this->assertStringContainsString('href="https://cucinow.co"', $campaign->content);
        $this->assertStringNotContainsString('onclick', $campaign->content);
        $this->assertStringNotContainsString('javascript:', $campaign->content);
        $this->assertStringNotContainsString('<script', $campaign->content);

        $subscriber = Subscriber::create(['email' => 'html@example.com', 'status' => 'subscribed', 'subscribed_at' => now()]);
        $rendered = (new NewsletterMail($campaign, $subscriber))->render();

        $this->assertStringContainsString('<strong>cleaner</strong>', $rendered);
        $this->assertStringNotContainsString('onclick', $rendered);
    }

    public function test_admin_can_upload_a_safe_newsletter_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->postJson(route('admin.campaigns.images.store'), [
            'image' => UploadedFile::fake()->image('clean-office.jpg', 1200, 630),
            'alt' => 'A professionally cleaned office',
        ]);

        $response->assertOk()->assertJsonPath('alt', 'A professionally cleaned office');
        $this->assertCount(1, Storage::disk('public')->allFiles('newsletters'));

        $url = $response->json('url');
        $sanitized = app(NewsletterHtmlSanitizer::class)->sanitize(
            '<img src="'.$url.'" alt="A clean office" onerror="alert(1)"><img src="https://example.com/tracker.gif" alt="Tracker">',
        );

        $this->assertStringContainsString('src="'.$url.'"', $sanitized);
        $this->assertStringContainsString('alt="A clean office"', $sanitized);
        $this->assertStringNotContainsString('onerror', $sanitized);
        $this->assertStringNotContainsString('example.com', $sanitized);
    }

    public function test_admin_can_send_an_immediate_newsletter_preview(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_active' => true, 'email' => 'admin-preview@example.com']);

        $this->actingAs($admin)->post(route('admin.campaigns.store'), [
            'action' => 'test',
            'name' => 'Preview campaign',
            'subject' => 'Preview subject',
            'preview_text' => 'Preview text',
            'content' => '<p>Preview <strong>message</strong>.</p>',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        Mail::assertSent(NewsletterPreviewMail::class, fn ($mail) => $mail->hasTo('admin-preview@example.com'));
        Mail::assertNothingQueued();
        $this->assertDatabaseMissing('newsletter_campaigns', ['name' => 'Preview campaign']);
    }

    public function test_admin_can_save_and_queue_a_campaign_from_the_editor(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->post(route('admin.campaigns.store'), [
            'action' => 'send',
            'name' => 'Immediate campaign',
            'subject' => 'Send this campaign',
            'content' => '<p>Campaign content.</p>',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $campaign = NewsletterCampaign::where('name', 'Immediate campaign')->firstOrFail();

        $this->assertSame('queued', $campaign->status);
        $this->assertNull($campaign->scheduled_at);
        Queue::assertPushed(SendNewsletterCampaign::class, fn ($job) => $job->campaignId === $campaign->id);
    }

    public function test_admin_can_edit_a_draft_campaign_but_not_sent_history(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $draft = NewsletterCampaign::create([
            'name' => 'Original draft',
            'subject' => 'Original subject',
            'content' => '<p>Original content.</p>',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.campaigns.edit', $draft))
            ->assertOk()
            ->assertSee('Edit campaign')
            ->assertSee('Original draft')
            ->assertSee('Original content.', false);

        $this->actingAs($admin)->patch(route('admin.campaigns.update', $draft), [
            'action' => 'draft',
            'name' => 'Updated draft',
            'subject' => 'Updated subject',
            'preview_text' => 'Updated preview',
            'content' => '<h2>Updated</h2><p onclick="alert(1)">Safe content.</p>',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $draft->refresh();
        $this->assertSame('Updated draft', $draft->name);
        $this->assertSame('draft', $draft->status);
        $this->assertStringContainsString('<h2', $draft->content);
        $this->assertStringNotContainsString('onclick', $draft->content);

        $draft->update(['status' => 'sent', 'sent_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.campaigns.update', $draft), [
            'action' => 'draft',
            'name' => 'Do not overwrite',
            'subject' => 'Do not overwrite',
            'content' => '<p>Do not overwrite.</p>',
        ])->assertRedirect(route('admin.campaigns.index'))->assertSessionHas('error');

        $this->assertSame('Updated draft', $draft->refresh()->name);
    }

    public function test_admin_can_duplicate_sent_campaign_as_a_clean_editable_draft(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $sent = NewsletterCampaign::create([
            'name' => 'August update',
            'subject' => 'A cleaner workplace',
            'preview_text' => 'August cleaning notes',
            'content' => '<h2>August</h2><p>Useful content.</p>',
            'status' => 'sent',
            'scheduled_at' => now()->subDay(),
            'sent_at' => now(),
            'recipient_count' => 125,
            'open_count' => 70,
            'click_count' => 15,
            'bounce_count' => 2,
            'unsubscribe_count' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.campaigns.duplicate', $sent))
            ->assertSessionHas('success');

        $copy = NewsletterCampaign::where('name', 'Copy of August update')->firstOrFail();

        $response->assertRedirect(route('admin.campaigns.edit', $copy));
        $this->assertSame($sent->subject, $copy->subject);
        $this->assertSame($sent->content, $copy->content);
        $this->assertSame('draft', $copy->status);
        $this->assertNull($copy->scheduled_at);
        $this->assertNull($copy->sent_at);
        $this->assertSame(0, $copy->recipient_count);
        $this->assertSame(0, $copy->open_count);
        $this->assertSame(0, $copy->click_count);
        $this->assertSame(0, $copy->bounce_count);
        $this->assertSame(0, $copy->unsubscribe_count);
    }

    public function test_campaign_analytics_reports_unique_engagement_and_delivery_problems(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $campaign = NewsletterCampaign::create([
            'name' => 'Analytics campaign',
            'subject' => 'Campaign performance',
            'content' => '<p>Measure this campaign.</p>',
            'status' => 'sent',
            'recipient_count' => 4,
            'sent_at' => now()->subDay(),
        ]);
        $eventNumber = 0;
        $recordEvent = function (string $type, string $emailId, string $recipient) use ($campaign, &$eventNumber): void {
            $eventNumber++;
            EmailEvent::create([
                'event_key' => 'analytics-event-'.$eventNumber,
                'provider' => 'resend',
                'event_type' => $type,
                'provider_email_id' => $emailId,
                'recipient' => $recipient,
                'metadata' => ['campaign_id' => (string) $campaign->id],
                'occurred_at' => now()->subDay()->addMinutes($eventNumber),
            ]);
        };

        $recordEvent('email.delivered', 'email-1', 'one@example.com');
        $recordEvent('email.delivered', 'email-2', 'two@example.com');
        $recordEvent('email.opened', 'email-1', 'one@example.com');
        $recordEvent('email.opened', 'email-1', 'one@example.com');
        $recordEvent('email.clicked', 'email-1', 'one@example.com');
        $recordEvent('email.suppressed', 'email-3', 'blocked@example.com');
        $recordEvent('email.failed', 'email-4', 'failed@example.com');
        $recordEvent('email.delivery_delayed', 'email-2', 'two@example.com');

        $this->actingAs($admin)
            ->get(route('admin.campaigns.analytics', $campaign))
            ->assertOk()
            ->assertSee('Campaign analytics')
            ->assertSee('Unique opens')
            ->assertSee('Blocked')
            ->assertSee('blocked@example.com')
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['unique_opens'] === 1
                && $metrics['total_opens'] === 2
                && $metrics['unique_clicks'] === 1
                && $metrics['delivered'] === 2
                && $metrics['blocked'] === 1
                && $metrics['failed'] === 1
                && $metrics['delayed'] === 1
                && $metrics['issues'] === 2);
    }

    public function test_resend_webhook_event_updates_campaign_metrics_once(): void
    {
        $subscriber = Subscriber::create(['email' => 'reader@example.com', 'status' => 'subscribed', 'subscribed_at' => now()]);
        $campaign = NewsletterCampaign::create(['name' => 'Test', 'subject' => 'Subject', 'content' => 'Content', 'status' => 'sent']);
        $payload = [
            'type' => 'email.opened',
            'created_at' => now()->toIso8601String(),
            'data' => [
                'email_id' => 'resend-email-123',
                'to' => [$subscriber->email],
                'tags' => ['campaign_id' => (string) $campaign->id, 'subscriber_id' => (string) $subscriber->id],
            ],
        ];
        $listener = new HandleResendEmailEvent;

        $listener->handle(new EmailOpened($payload));
        $listener->handle(new EmailOpened($payload));

        $this->assertDatabaseCount('email_events', 1);
        $this->assertSame(1, $campaign->refresh()->open_count);
    }

    public function test_unsigned_resend_webhook_requests_are_rejected(): void
    {
        $this->postJson('/resend/webhook', ['type' => 'email.opened', 'data' => []])->assertForbidden();
    }

    public function test_customer_email_templates_render_with_business_details(): void
    {
        $customer = Customer::create(['name' => 'Aina Rahman', 'phone' => '0111111111', 'email' => 'aina@example.com']);
        $service = Service::create(['code' => 'office-cleaning', 'name' => 'Office Cleaning', 'unit' => 'job', 'is_active' => true]);
        $siteVisit = SiteVisitRequest::create(['reference_number' => 'SV-RENDER-1', 'customer_id' => $customer->id, 'service_id' => $service->id, 'status' => 'new', 'space_type' => 'office', 'preferred_date' => now()->addDay(), 'preferred_time_slot' => 'morning', 'site_address' => 'Example Tower', 'postcode' => '40160']);
        $quote = Quote::create(['quote_number' => 'Q-RENDER-1', 'customer_id' => $customer->id, 'status' => 'sent', 'subtotal' => 250, 'total' => 250, 'valid_until' => now()->addDays(14), 'service_address' => 'Example Tower']);
        $quote->items()->create(['service_id' => $service->id, 'description' => 'Office Cleaning', 'quantity' => 1, 'unit' => 'job', 'unit_price' => 250, 'amount' => 250]);
        $invoice = Invoice::create(['invoice_number' => 'INV-RENDER-1', 'customer_id' => $customer->id, 'quote_id' => $quote->id, 'status' => 'overdue', 'issued_at' => today(), 'due_at' => today()->subDay(), 'subtotal' => 250, 'total' => 250, 'balance' => 250]);
        $invoice->items()->create(['description' => 'Office Cleaning', 'quantity' => 1, 'unit' => 'job', 'unit_price' => 250, 'amount' => 250]);
        $booking = Booking::create(['booking_number' => 'BK-RENDER-1', 'customer_id' => $customer->id, 'quote_id' => $quote->id, 'service_id' => $service->id, 'status' => 'confirmed', 'scheduled_start' => now()->addDay(), 'service_address' => 'Example Tower', 'total' => 250]);
        $payment = Payment::create(['payment_number' => 'PAY-RENDER-1', 'invoice_id' => $invoice->id, 'method' => 'fpx', 'status' => 'completed', 'amount' => 100, 'paid_at' => now()]);
        $subscriber = Subscriber::create(['email' => 'reader@example.com', 'status' => 'subscribed', 'subscribed_at' => now()]);
        $campaign = NewsletterCampaign::create(['name' => 'Render', 'subject' => 'Cleaning note', 'content' => 'Useful content', 'status' => 'draft']);

        $mailables = [
            new SiteVisitConfirmationMail($siteVisit),
            new NewSiteVisitNotificationMail($siteVisit),
            new QuoteMail($quote),
            new InvoiceMail($invoice),
            new BookingConfirmationMail($booking),
            new PaymentReceiptMail($payment),
            new InvoiceOverdueReminderMail($invoice),
            new NewsletterMail($campaign, $subscriber),
            new SubscriberWelcomeMail($subscriber),
        ];

        foreach ($mailables as $mailable) {
            $this->assertStringContainsString('CuciNow.co', $mailable->render());
        }
    }
}
