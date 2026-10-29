<?php

namespace Tests\Feature;

use App\Mail\ProformaInvoiceMail;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProformaInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function quote(User $creator): Quote
    {
        $customer = Customer::create([
            'name' => 'Aina Rahman', 'company_name' => 'Acme Industries Sdn Bhd',
            'phone' => '01112345678', 'email' => 'aina@acme.example',
        ]);
        $quote = Quote::create([
            'quote_number' => 'Q-PROFORMA-1', 'customer_id' => $customer->id,
            'created_by_user_id' => $creator->id, 'status' => 'draft',
            'title' => 'RE: QUOTATION FOR INITIAL CLEANING SERVICE AT ACME INDUSTRIES SDN BHD',
            'document_date' => '2026-10-29', 'valid_until' => '2026-11-12',
            'deposit_percentage' => 40, 'sales_person_user_id' => $creator->id,
            'sales_person_name' => 'Nur Sales', 'sales_person_title' => 'SALES DEPARTMENT OF CUCINOW.CO',
            'subtotal' => 1000, 'tax_rate' => 8, 'tax_amount' => 80, 'total' => 1080,
        ]);
        $quote->items()->create([
            'description' => 'Initial cleaning service', 'quantity' => 1, 'unit' => 'job',
            'unit_price' => 1000, 'amount' => 1000, 'notes' => '<p><strong>Included:</strong> Main office</p>',
        ]);

        return $quote;
    }

    public function test_quotation_commercial_details_and_sales_signature_are_editable_and_rendered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sales = User::factory()->create([
            'role' => 'office', 'sales_signature_name' => 'Farah Sales',
            'sales_signature_title' => 'SALES DEPARTMENT OF CUCINOW.CO',
        ]);
        $quote = $this->quote($admin);

        $this->actingAs($admin)->patch(route('admin.quotes.update', $quote), [
            'title' => 'RE: QUOTATION FOR PERIODIC CLEANING AT ACME INDUSTRIES SDN BHD',
            'document_date' => '2026-10-30', 'deposit_percentage' => 35,
            'sales_person_user_id' => $sales->id, 'status' => 'draft', 'internal_notes' => '',
        ])->assertSessionHasNoErrors();

        $quote->refresh();
        $this->assertSame('Farah Sales', $quote->sales_person_name);
        $this->assertSame('378.00', number_format($quote->deposit_amount, 2, '.', ''));
        $this->assertSame('702.00', number_format($quote->balance_amount, 2, '.', ''));
        $this->get(route('admin.quotes.print', $quote))->assertOk()
            ->assertSee('30 Oct 2026')->assertSee('Grand Total')->assertSee('Farah Sales')
            ->assertSee('Deposit payment of 35% to confirm slot')->assertSee('RM 378.00');
    }

    public function test_proforma_invoice_is_snapshotted_from_quote_and_can_be_printed_and_emailed(): void
    {
        Mail::fake();
        $office = User::factory()->create(['role' => 'office']);
        $quote = $this->quote($office);

        $response = $this->actingAs($office)->post(route('admin.proforma-invoices.store', $quote));
        $proforma = $quote->fresh()->proformaInvoice;
        $response->assertRedirect(route('admin.proforma-invoices.show', $proforma));

        $this->assertDatabaseHas('proforma_invoices', [
            'id' => $proforma->id, 'quote_id' => $quote->id, 'deposit_percentage' => 40,
            'sales_person_name' => 'Nur Sales', 'total' => 1080,
        ]);
        $this->assertDatabaseHas('proforma_invoice_items', [
            'proforma_invoice_id' => $proforma->id, 'description' => 'Initial cleaning service', 'amount' => 1000,
        ]);

        $this->get(route('admin.proforma-invoices.index'))->assertOk()->assertSee($proforma->proforma_number);
        $this->get(route('admin.proforma-invoices.show', $proforma))->assertOk()
            ->assertSee('Grand total')->assertSee('RM 432.00')->assertSee('RM 648.00');
        $this->get(route('admin.proforma-invoices.print', $proforma))->assertOk()
            ->assertSee('Grand Total')->assertSee('Nur Sales');

        $this->post(route('admin.proforma-invoices.send', $proforma), ['recipients' => 'finance@acme.example'])
            ->assertSessionHasNoErrors();
        Mail::assertQueued(ProformaInvoiceMail::class);
        $this->assertSame($office->id, $proforma->fresh()->sent_by_user_id);
    }

    public function test_field_users_cannot_access_proforma_invoices_and_duplicate_creation_is_prevented(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $field = User::factory()->create(['role' => 'field']);
        $quote = $this->quote($admin);

        $this->actingAs($field)->get(route('admin.proforma-invoices.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.proforma-invoices.store', $quote))->assertRedirect();
        $first = $quote->fresh()->proformaInvoice;
        $this->post(route('admin.proforma-invoices.store', $quote))->assertRedirect(route('admin.proforma-invoices.show', $first));
        $this->assertDatabaseCount('proforma_invoices', 1);
    }
}
