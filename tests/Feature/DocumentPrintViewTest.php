<?php

namespace Tests\Feature;

use App\Mail\InvoiceMail;
use App\Mail\QuoteMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentPrintViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_print_view_reuses_the_email_template_and_content(): void
    {
        $customer = Customer::create(['name' => 'Print Client', 'phone' => '01112345678', 'email' => 'print@example.com', 'company_name' => 'Print Client Sdn Bhd']);
        $quote = Quote::create([
            'quote_number' => 'Q-PRINT-1', 'customer_id' => $customer->id,
            'status' => 'draft', 'subtotal' => 500, 'total' => 500,
            'service_address' => 'Print Office', 'postcode' => '40160', 'city' => 'Shah Alam', 'state' => 'Selangor',
            'contact_people' => [['name' => 'Aina', 'role' => 'Facilities Manager', 'email' => 'aina@example.com']],
        ]);
        $quote->items()->create(['description' => 'Deep cleaning', 'quantity' => 1, 'unit' => 'job', 'unit_price' => 500, 'amount' => 500]);

        $this->get(route('admin.quotes.print', $quote))->assertRedirect(route('login'));
        $response = $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get(route('admin.quotes.print', $quote));

        $response->assertOk()->assertViewIs('emails.quote')->assertViewHas('printMode', true)
            ->assertSee('Q-PRINT-1')->assertSee('Deep cleaning')->assertSee('Aina')->assertSee('Print Client Sdn Bhd')
            ->assertSee('document-print-toolbar', false)->assertSee('window.print()', false)
            ->assertSee('document-totals-table', false)
            ->assertSee('border-radius:18px!important', false)
            ->assertSee('background:#f7f4ec!important', false)
            ->assertSee('Grand Total')
            ->assertDontSee('Reply to this email or contact us on WhatsApp')
            ->assertDontSee('Discuss this quotation')
            ->assertDontSee('tr,img{break-inside:avoid', false);

        $email = (new QuoteMail($quote->load(['customer', 'items'])))->render();
        $this->assertStringContainsString('Deep cleaning', $email);
        $this->assertStringContainsString('Project contacts', $email);
        $this->assertStringContainsString('Print Client Sdn Bhd', $email);
        $this->assertStringContainsString('Reply to this email or contact us on WhatsApp', $email);
        $this->assertStringContainsString('Discuss this quotation', $email);
        $this->assertStringNotContainsString('document-print-toolbar', $email);
    }

    public function test_invoice_print_view_reuses_the_email_template_and_content(): void
    {
        $customer = Customer::create(['name' => 'Invoice Client', 'phone' => '01187654321', 'email' => 'invoice@example.com', 'company_name' => 'Invoice Client Sdn Bhd']);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-PRINT-1', 'customer_id' => $customer->id,
            'status' => 'draft', 'subtotal' => 750, 'total' => 750, 'balance' => 750,
            'payment_terms' => 'Due within 14 days.',
        ]);
        $invoice->items()->create(['description' => 'Carpet cleaning', 'quantity' => 1, 'unit' => 'job', 'unit_price' => 750, 'amount' => 750]);

        $response = $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get(route('admin.invoices.print', $invoice));

        $response->assertOk()->assertViewIs('emails.invoice')->assertViewHas('printMode', true)
            ->assertSee('INV-PRINT-1')->assertSee('Carpet cleaning')->assertSee('Balance due')->assertSee('Invoice Client Sdn Bhd')
            ->assertSee('document-print-toolbar', false);

        $email = (new InvoiceMail($invoice->load(['customer', 'items'])))->render();
        $this->assertStringContainsString('Carpet cleaning', $email);
        $this->assertStringContainsString('Balance due', $email);
        $this->assertStringContainsString('Invoice Client Sdn Bhd', $email);
        $this->assertStringNotContainsString('document-print-toolbar', $email);
    }
}
