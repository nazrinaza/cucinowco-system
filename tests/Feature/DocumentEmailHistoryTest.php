<?php

namespace Tests\Feature;

use App\Listeners\HandleResendEmailEvent;
use App\Listeners\RecordDocumentEmailSent;
use App\Mail\InvoiceMail;
use App\Mail\QuoteMail;
use App\Models\Customer;
use App\Models\EmailEvent;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Support\DocumentEmailHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Resend\Laravel\Events\EmailDelivered;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class DocumentEmailHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function document(string $type = 'quote'): Quote|Invoice
    {
        $customer = Customer::create(['name' => 'Email history client', 'phone' => '01112345678', 'email' => 'history@example.com']);

        return $type === 'quote'
            ? Quote::create(['quote_number' => 'Q-'.fake()->unique()->numerify('######'), 'customer_id' => $customer->id])
            : Invoice::create(['invoice_number' => 'INV-'.fake()->unique()->numerify('######'), 'customer_id' => $customer->id]);
    }

    private function sentEvent(string $attempt, string $providerId): MessageSent
    {
        $email = (new Email)->from('team@example.com')->to('history@example.com')->subject('Test')->text('Test email');
        $email->getHeaders()->addTextHeader('X-CuciNow-Delivery-Attempt', $attempt);
        $email->getHeaders()->addTextHeader('X-Resend-Email-ID', $providerId);

        return new MessageSent(new SentMessage(new SymfonySentMessage($email, Envelope::create($email))));
    }

    public function test_each_resend_is_tracked_separately_and_not_marked_sent_while_queued(): void
    {
        Mail::fake();
        config(['mail.default' => 'resend']);
        $admin = User::factory()->create(['name' => 'History Admin', 'is_active' => true]);
        $quote = $this->document();
        $this->actingAs($admin);
        $this->post(route('admin.quotes.send', $quote))->assertSessionHas('success');
        $this->post(route('admin.quotes.send', $quote))->assertSessionHas('success');
        $queued = EmailEvent::where('event_type', 'app.queued')->get();
        $this->assertCount(2, $queued);
        $this->assertNotSame($queued[0]->metadata['delivery_attempt_id'], $queued[1]->metadata['delivery_attempt_id']);
        $this->assertSame('History Admin', $queued[0]->metadata['requested_by']);
        $this->assertSame('draft', $quote->fresh()->status);
        $this->assertNull($quote->fresh()->sent_at);

        $messages = Mail::queued(QuoteMail::class);
        $this->assertNotSame($messages[0]->headers()->text['Resend-Idempotency-Key'], $messages[1]->headers()->text['Resend-Idempotency-Key']);
        $restored = unserialize(serialize($messages[0]));
        $this->assertSame($messages[0]->headers()->text, $restored->headers()->text);
        $this->assertSame($messages[0]->deliveryAttemptId, $restored->envelope()->metadata['delivery_attempt_id']);

        $this->get(route('admin.quotes.show', $quote))->assertOk()->assertSee('Email sending history')->assertSee('Queued')->assertSee('History Admin');
    }

    public function test_provider_acceptance_and_untagged_delivery_events_are_linked_with_malaysia_times(): void
    {
        Mail::fake();
        config(['mail.default' => 'resend', 'app.timezone' => 'Asia/Kuala_Lumpur']);
        $invoice = $this->document('invoice');
        app(DocumentEmailHistory::class)->queue($invoice, new InvoiceMail($invoice), 'invoice');
        $attempt = EmailEvent::first()->metadata['delivery_attempt_id'];
        $listener = new RecordDocumentEmailSent;
        $listener->handle($this->sentEvent($attempt, 'resend-history-1'));
        $listener->handle($this->sentEvent($attempt, 'resend-history-1'));
        $this->assertSame(1, EmailEvent::where('event_type', 'app.sent')->count());
        $this->assertSame('sent', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->sent_at);

        $webhook = new EmailDelivered([
            'type' => 'email.delivered', 'created_at' => '2026-09-09T06:30:00Z',
            'data' => ['email_id' => 'resend-history-1', 'to' => ['history@example.com']],
        ]);
        app(HandleResendEmailEvent::class)->handle($webhook);
        app(HandleResendEmailEvent::class)->handle($webhook);
        $this->assertSame(1, EmailEvent::where('event_type', 'email.delivered')->count());
        $delivered = EmailEvent::where('event_type', 'email.delivered')->first();
        $this->assertSame('2026-09-09 14:30:00', $delivered->occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame((string) $invoice->id, $delivered->metadata['invoice_id']);

        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get(route('admin.invoices.show', $invoice))->assertOk()->assertSee('Delivered')->assertSee('02:30:00 PM')->assertSee('Sent to provider');
        $this->assertSame(0, app(DocumentEmailHistory::class)->forDocument($this->document('invoice'))->total());
    }

    public function test_early_untagged_webhook_remains_visible_after_the_provider_id_is_recorded(): void
    {
        Mail::fake();
        config(['mail.default' => 'resend']);
        $quote = $this->document();
        app(DocumentEmailHistory::class)->queue($quote, new QuoteMail($quote), 'quotation');
        $attempt = EmailEvent::first()->metadata['delivery_attempt_id'];
        app(HandleResendEmailEvent::class)->handle(new EmailDelivered([
            'type' => 'email.delivered', 'created_at' => now()->toIso8601String(),
            'data' => ['email_id' => 'early-delivery', 'to' => ['history@example.com']],
        ]));
        app(RecordDocumentEmailSent::class)->handle($this->sentEvent($attempt, 'early-delivery'));
        $this->assertSame(3, app(DocumentEmailHistory::class)->forDocument($quote)->total());
    }

    public function test_failed_queue_or_exhausted_retries_are_recorded_without_claiming_a_send(): void
    {
        Mail::fake();
        $quote = $this->document();
        app(DocumentEmailHistory::class)->queue($quote, new QuoteMail($quote), 'quotation');
        $mail = Mail::queued(QuoteMail::class)->first();
        $mail->failed(new \RuntimeException('Private transport details'));
        $mail->failed(new \RuntimeException('Private transport details'));
        $this->assertSame(1, EmailEvent::where('event_type', 'app.failed')->count());
        $this->assertNull($quote->fresh()->sent_at);
        $this->actingAs(User::factory()->create(['is_active' => true]))->get(route('admin.quotes.show', $quote))
            ->assertSee('Sending failed')->assertDontSee('Private transport details');
    }

    public function test_transport_hook_runs_for_real_mailable_dispatch_without_sending_external_email(): void
    {
        config(['queue.default' => 'sync', 'mail.default' => 'array']);
        $quote = $this->document();
        app(DocumentEmailHistory::class)->queue($quote, new QuoteMail($quote), 'quotation');
        $this->assertSame(1, EmailEvent::where('event_type', 'app.previewed')->count());
        $this->assertSame(0, EmailEvent::where('event_type', 'app.sent')->count());
        $this->assertSame('draft', $quote->fresh()->status);
    }

    public function test_queue_submission_errors_leave_an_audit_record_and_a_clear_admin_error(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        $invoice = $this->document('invoice');
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->post(route('admin.invoices.send', $invoice))->assertSessionHas('error');
        $this->assertSame(1, EmailEvent::where('event_type', 'app.queued')->count());
        $this->assertSame(1, EmailEvent::where('event_type', 'app.failed')->count());
        $this->assertSame('draft', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->sent_at);
    }

    public function test_legacy_manual_status_is_not_presented_as_confirmed_delivery(): void
    {
        $invoice = $this->document('invoice');
        $invoice->update(['status' => 'sent', 'sent_at' => now()]);
        $this->actingAs(User::factory()->create(['is_active' => true]))->get(route('admin.invoices.show', $invoice))
            ->assertOk()->assertSee('Older send/status record')->assertSee('does not verify email delivery');
    }
}
