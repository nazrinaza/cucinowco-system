<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteItemEditingTest extends TestCase
{
    use RefreshDatabase;

    private function quotation(): Quote
    {
        return Quote::create([
            'quote_number' => 'Q-'.fake()->unique()->numerify('######'),
            'customer_id' => Customer::create(['name' => 'Test client', 'phone' => '01112345678'])->id,
            'status' => 'sent', 'sent_at' => now(), 'subtotal' => 250, 'total' => 250,
        ]);
    }

    private function line(array $values = []): array
    {
        return array_merge(['description' => 'Carpet cleaning add-on', 'quantity' => '1', 'unit' => 'job', 'unit_price' => '80.00'], $values);
    }

    public function test_services_can_be_edited_added_and_removed_with_recalculated_totals(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $quote = $this->quotation();
        $quote->update(['tax_rate' => 8]);
        $service = Service::create(['code' => 'office', 'name' => 'Office cleaning']);
        $original = $quote->items()->create([...$this->line(['unit_price' => 200]), 'service_id' => $service->id, 'amount' => 200]);
        $removed = $quote->items()->create([...$this->line(['unit_price' => 50]), 'amount' => 50]);

        $this->actingAs($admin)->get(route('admin.quotes.show', $quote))->assertOk()->assertSee('Add service line');
        $this->patch(route('admin.quotes.items.update', $quote), [
            'items' => [
                $this->line(['id' => $original->id, 'description' => 'Office deep cleaning', 'quantity' => '2.50', 'unit_price' => '100.05', 'notes' => 'Include meeting rooms']),
                $this->line(),
            ],
            'discount' => '10.00',
        ])->assertRedirect(route('admin.quotes.show', $quote))->assertSessionHasNoErrors();

        $quote->refresh();
        $this->assertSame('330.13', $quote->subtotal);
        $this->assertSame('25.61', $quote->tax_amount);
        $this->assertSame('345.74', $quote->total);
        $this->assertSame('draft', $quote->status);
        $this->assertNull($quote->sent_at);
        $this->assertSame(2, $quote->items()->count());
        $this->assertDatabaseHas('quote_items', ['id' => $original->id, 'service_id' => $service->id, 'amount' => 250.13, 'notes' => 'Include meeting rooms']);
        $this->assertDatabaseMissing('quote_items', ['id' => $removed->id]);
        $this->assertDatabaseHas('quote_items', ['quote_id' => $quote->id, 'description' => 'Carpet cleaning add-on', 'amount' => 80]);

        config(['company.sst_enabled' => false]);
        $this->post(route('admin.quotes.convert', $quote))->assertRedirect();
        $this->assertDatabaseHas('invoices', ['quote_id' => $quote->id, 'subtotal' => 330.13, 'discount' => 10, 'total' => 320.13]);
        $this->assertDatabaseHas('invoice_items', ['description' => 'Carpet cleaning add-on', 'amount' => 80]);
    }

    public function test_invalid_edits_do_not_change_saved_items_or_totals(): void
    {
        $quote = $this->quotation();
        $item = $quote->items()->create([...$this->line(), 'amount' => 80]);
        $other = $this->quotation()->items()->create([...$this->line(), 'amount' => 80]);
        $this->actingAs(User::factory()->create(['is_active' => true]));

        $this->patch(route('admin.quotes.items.update', $quote), ['items' => [$this->line(['id' => $other->id])], 'discount' => 0])
            ->assertSessionHasErrors('items.0.id');
        $this->patch(route('admin.quotes.items.update', $quote), ['items' => [$this->line(['quantity' => 0, 'unit_price' => -1])], 'discount' => 0])
            ->assertSessionHasErrors(['items.0.quantity', 'items.0.unit_price']);
        $this->patch(route('admin.quotes.items.update', $quote), ['items' => [$this->line(['amount' => 1])], 'discount' => 0])
            ->assertSessionHasErrors('items.0');
        $this->patch(route('admin.quotes.items.update', $quote), ['items' => [$this->line()], 'discount' => 81])
            ->assertSessionHasErrors('discount');
        $this->patch(route('admin.quotes.items.update', $quote), ['items' => [], 'discount' => 0])
            ->assertSessionHasErrors('items');

        $this->assertSame('250.00', $quote->fresh()->total);
        $this->assertSame([$item->id], $quote->items()->pluck('id')->all());
    }

    public function test_invoiced_and_booked_quotations_cannot_be_repriced(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));
        foreach (['invoice', 'booking'] as $relation) {
            $quote = $this->quotation();
            if ($relation === 'invoice') {
                $quote->invoice()->create(['invoice_number' => 'INV-LOCK', 'customer_id' => $quote->customer_id]);
            } else {
                $quote->booking()->create(['booking_number' => 'BK-LOCK', 'customer_id' => $quote->customer_id, 'service_address' => 'Shah Alam']);
            }
            $this->patch(route('admin.quotes.items.update', $quote), ['items' => [$this->line()], 'discount' => 0])
                ->assertSessionHasErrors('items');
            $this->assertSame('250.00', $quote->fresh()->total);
            $this->assertSame(0, $quote->items()->count());
        }
    }

    public function test_editing_services_requires_authentication(): void
    {
        $this->patch(route('admin.quotes.items.update', $this->quotation()), ['items' => [$this->line()], 'discount' => 0])
            ->assertRedirect(route('login'));
    }
}
