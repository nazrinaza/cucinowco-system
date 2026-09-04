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
use App\Mail\PaymentReceiptMail;
use App\Mail\QuoteMail;
use App\Mail\SiteVisitConfirmationMail;
use App\Mail\SubscriberWelcomeMail;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NewsletterCampaign;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Service;
use App\Models\SiteVisitRequest;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
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
