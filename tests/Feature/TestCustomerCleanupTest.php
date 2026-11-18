<?php

namespace Tests\Feature;

use App\Models\BookingCalendarSync;
use App\Models\Customer;
use App\Models\EmailEvent;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\ReferralSource;
use App\Models\SiteVisitPhoto;
use App\Models\SiteVisitRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TestCustomerCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_remove_a_test_customer_and_all_linked_operational_records(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $source = ReferralSource::create([
            'name' => 'Test agent', 'code' => 'test-agent', 'channel' => 'agent',
            'is_active' => true, 'lead_count' => 1,
        ]);
        $customer = Customer::create(['name' => 'Test Customer', 'phone' => '0110000000']);
        $siteVisit = SiteVisitRequest::create([
            'reference_number' => 'SV-DELETE-TEST', 'customer_id' => $customer->id,
            'referral_source_id' => $source->id, 'referral_code' => $source->code,
            'status' => 'new', 'space_type' => 'office', 'site_address' => 'Test address',
        ]);
        Storage::disk('local')->put('site-visits/test/photo.jpg', 'test image');
        SiteVisitPhoto::create([
            'site_visit_request_id' => $siteVisit->id, 'uploaded_by_user_id' => $admin->id,
            'phase' => 'gallery', 'path' => 'site-visits/test/photo.jpg', 'mime_type' => 'image/jpeg',
            'original_name' => 'photo.jpg',
        ]);
        $quote = Quote::create([
            'quote_number' => 'Q-DELETE-TEST', 'customer_id' => $customer->id,
            'site_visit_request_id' => $siteVisit->id, 'status' => 'accepted', 'subtotal' => 500, 'total' => 500,
        ]);
        $quote->items()->create([
            'description' => 'Test cleaning', 'quantity' => 1, 'unit' => 'job',
            'unit_price' => 500, 'amount' => 500,
        ]);
        $siteVisit->update(['quote_id' => $quote->id]);
        $proforma = $quote->proformaInvoice()->create([
            'proforma_number' => 'PI-DELETE-TEST', 'customer_id' => $customer->id,
            'title' => 'Test proforma', 'status' => 'draft', 'issued_at' => now(),
            'subtotal' => 500, 'total' => 500, 'deposit_percentage' => 40,
        ]);
        $proforma->items()->create([
            'description' => 'Test cleaning', 'quantity' => 1, 'unit' => 'job',
            'unit_price' => 500, 'amount' => 500,
        ]);
        $booking = $customer->bookings()->create([
            'booking_number' => 'BK-DELETE-TEST', 'quote_id' => $quote->id,
            'status' => 'pending', 'service_address' => 'Test address', 'total' => 500,
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-DELETE-TEST', 'customer_id' => $customer->id,
            'quote_id' => $quote->id, 'booking_id' => $booking->id, 'status' => 'paid',
            'issued_at' => now(), 'subtotal' => 500, 'total' => 500, 'amount_paid' => 500, 'balance' => 0,
        ]);
        $invoice->items()->create([
            'description' => 'Test cleaning', 'quantity' => 1, 'unit' => 'job',
            'unit_price' => 500, 'amount' => 500,
        ]);
        $invoice->payments()->create([
            'payment_number' => 'PAY-DELETE-TEST', 'method' => 'cash', 'status' => 'completed',
            'amount' => 500, 'paid_at' => now(),
        ]);
        EmailEvent::create([
            'event_key' => str_repeat('a', 64), 'provider' => 'resend', 'event_type' => 'email.delivered',
            'provider_email_id' => 'email-delete-test', 'recipient' => 'test@example.com',
            'metadata' => ['category' => 'invoice', 'invoice_id' => (string) $invoice->id], 'occurred_at' => now(),
        ]);
        EmailEvent::create([
            'event_key' => str_repeat('b', 64), 'provider' => 'resend', 'event_type' => 'email.clicked',
            'provider_email_id' => 'email-delete-test', 'recipient' => 'test@example.com',
            'metadata' => null, 'occurred_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.customers.show', $customer))
            ->assertOk()->assertSee('Test-data cleanup')->assertSee('RM 500.00');
        $this->get(route('admin.dashboard'))->assertSee('RM 500.00');

        $this->delete(route('admin.customers.test-data.destroy', $customer), [
            'confirmation' => "DELETE CUSTOMER {$customer->id}",
            'confirm_test_data' => '1',
        ])->assertRedirect(route('admin.customers.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('site_visit_requests', ['id' => $siteVisit->id]);
        $this->assertDatabaseMissing('quotes', ['id' => $quote->id]);
        $this->assertDatabaseMissing('proforma_invoices', ['id' => $proforma->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseCount('email_events', 0);
        $this->assertSame(0, $source->fresh()->lead_count);
        Storage::disk('local')->assertMissing('site-visits/test/photo.jpg');
        $this->get(route('admin.dashboard'))->assertSee('RM 0.00');
    }

    public function test_cleanup_requires_exact_confirmation_and_admin_permission(): void
    {
        $customer = Customer::create(['name' => 'Protected Customer', 'phone' => '0111111111']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->delete(route('admin.customers.test-data.destroy', $customer), [
            'confirmation' => 'DELETE', 'confirm_test_data' => '1',
        ])->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);

        $office = User::factory()->create(['role' => 'office']);
        $this->actingAs($office)->delete(route('admin.customers.test-data.destroy', $customer), [
            'confirmation' => "DELETE CUSTOMER {$customer->id}", 'confirm_test_data' => '1',
        ])->assertForbidden();
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_cleanup_is_blocked_while_a_google_calendar_event_is_active(): void
    {
        $customer = Customer::create(['name' => 'Calendar Customer', 'phone' => '0112222222']);
        $booking = $customer->bookings()->create([
            'booking_number' => 'BK-CALENDAR-TEST', 'status' => 'confirmed',
            'scheduled_start' => now()->addDay(), 'service_address' => 'Test address', 'total' => 0,
        ]);
        BookingCalendarSync::updateOrCreate(['booking_id' => $booking->id], [
            'google_event_id' => 'active-test-event', 'status' => 'synced',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('admin.customers.test-data.destroy', $customer), [
                'confirmation' => "DELETE CUSTOMER {$customer->id}", 'confirm_test_data' => '1',
            ])->assertSessionHasErrors('customer');

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }
}
