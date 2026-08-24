<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_require_authentication(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_an_active_admin_can_open_the_dashboard(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/admin')
            ->assertOk()
            ->assertSee('CuciNow operations');
    }

    public function test_an_admin_can_convert_a_quote_to_an_invoice(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $customer = Customer::create(['name' => 'Example Customer', 'phone' => '0111111111']);
        $quote = Quote::create([
            'quote_number' => 'Q-202608-TEST1', 'customer_id' => $customer->id,
            'status' => 'sent', 'subtotal' => 250, 'total' => 250,
        ]);
        $quote->items()->create(['description' => 'Office Cleaning', 'quantity' => 1, 'unit' => 'job', 'unit_price' => 250, 'amount' => 250]);

        $this->actingAs($user)->post(route('admin.quotes.convert', $quote))->assertRedirect();

        $this->assertDatabaseHas('invoices', ['quote_id' => $quote->id, 'subtotal' => 250, 'tax_rate' => 0, 'balance' => 250]);
        $this->assertDatabaseHas('invoice_items', ['description' => 'Office Cleaning', 'amount' => 250]);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'accepted']);
    }
}
