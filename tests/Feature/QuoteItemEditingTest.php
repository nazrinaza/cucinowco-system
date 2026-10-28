<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use App\Mail\QuoteMail;
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
        $this->assertDatabaseHas('quote_items', ['id' => $original->id, 'service_id' => $service->id, 'amount' => 250.13, 'notes' => '<p>Include meeting rooms</p>']);
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

    public function test_customer_facing_scope_formatting_is_sanitized_for_quote_and_email(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));
        $quote = $this->quotation();
        $quote->update(['customer_notes' => 'PRIVATE CUSTOMER NOTE']);
        $unsafe = '<p><strong>Included</strong> and <em>carefully handled</em></p><ul><li>Lobby</li><li>Meeting rooms</li></ul><hr><script>alert(1)</script><a href="https://bad.example">bad link</a>';

        $this->patch(route('admin.quotes.items.update', $quote), [
            'items' => [$this->line(['notes' => $unsafe])],
            'discount' => 0,
        ])->assertSessionHasNoErrors();

        $item = $quote->items()->firstOrFail();
        $this->assertStringContainsString('<strong>Included</strong>', $item->notes);
        $this->assertStringContainsString('<ul><li>Lobby</li><li>Meeting rooms</li></ul>', $item->notes);
        $this->assertStringNotContainsString('script', $item->notes);
        $this->assertStringNotContainsString('href', $item->notes);

        $this->get(route('admin.quotes.show', $quote))
            ->assertOk()
            ->assertSee('<strong>Included</strong>', false)
            ->assertSee('Project &amp; payment terms', false)
            ->assertDontSee('PRIVATE CUSTOMER NOTE')
            ->assertDontSee('alert(1)');

        $email = (new QuoteMail($quote->fresh(['customer', 'items'])))->render();
        $this->assertStringContainsString('<strong>Included</strong>', $email);
        $this->assertStringContainsString('padding-left:21px', $email);
        $this->assertStringContainsString('Project &amp; payment terms', $email);
        $this->assertStringNotContainsString('PRIVATE CUSTOMER NOTE', $email);
        $this->assertStringNotContainsString('alert(1)', $email);
    }

    public function test_multiple_project_contacts_are_saved_and_displayed_on_quotation_and_email(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $quote = $this->quotation();

        $this->actingAs($admin)->patch(route('admin.quotes.contacts.update', $quote), [
            'contacts' => [
                ['name' => 'Aina Rahman', 'role' => 'Facilities Manager', 'phone' => '011-1111 2222', 'email' => 'AINA@example.com'],
                ['name' => 'Kumar Dev', 'role' => 'Site Supervisor', 'phone' => '012-3333 4444', 'email' => ''],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $quote->refresh();
        $this->assertCount(2, $quote->contact_people);
        $this->assertSame('aina@example.com', $quote->contact_people[0]['email']);
        $this->assertSame($admin->id, $quote->last_edited_by_user_id);

        $this->get(route('admin.quotes.show', $quote))
            ->assertOk()->assertSee('Project contacts')->assertSee('Aina Rahman')->assertSee('Facilities Manager')->assertSee('Kumar Dev');

        $email = (new QuoteMail($quote->load(['customer', 'items'])))->render();
        $this->assertStringContainsString('Project contacts', $email);
        $this->assertStringContainsString('Aina Rahman', $email);
        $this->assertStringContainsString('aina@example.com', $email);
    }

    public function test_project_contacts_require_a_reachable_phone_or_email_and_are_limited_to_ten(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));
        $quote = $this->quotation();

        $this->patch(route('admin.quotes.contacts.update', $quote), [
            'contacts' => [['name' => 'Unreachable person', 'role' => 'Manager', 'phone' => '', 'email' => '']],
        ])->assertSessionHasErrors(['contacts.0.phone', 'contacts.0.email']);

        $this->patch(route('admin.quotes.contacts.update', $quote), [
            'contacts' => array_fill(0, 11, ['name' => 'Contact', 'phone' => '0111234567']),
        ])->assertSessionHasErrors('contacts');

        $this->assertNull($quote->fresh()->contact_people);
    }

    public function test_editing_project_contacts_requires_authentication(): void
    {
        $this->patch(route('admin.quotes.contacts.update', $this->quotation()), [
            'contacts' => [['name' => 'Contact', 'phone' => '0111234567']],
        ])->assertRedirect(route('login'));
    }
}
