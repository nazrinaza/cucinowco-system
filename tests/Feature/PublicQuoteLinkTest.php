<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicQuoteLinkTest extends TestCase
{
    use RefreshDatabase;

    private function quote(string $status = 'sent'): Quote
    {
        $customer = Customer::create([
            'name' => 'Public Quote Client', 'company_name' => 'Client Sdn Bhd',
            'email' => 'client@example.com', 'phone' => '01112345678',
        ]);
        $quote = Quote::create([
            'quote_number' => 'Q-PUBLIC-1', 'customer_id' => $customer->id,
            'status' => $status, 'subtotal' => 1300, 'total' => 1300,
            'service_address' => 'Example Tower, Kuala Lumpur',
        ]);
        $quote->items()->create([
            'description' => 'Office deep cleaning', 'quantity' => 1, 'unit' => 'job',
            'unit_price' => 1300, 'amount' => 1300,
        ]);

        return $quote;
    }

    public function test_signed_public_link_displays_a_final_quotation_without_login(): void
    {
        $quote = $this->quote();
        $url = URL::signedRoute('quotes.public', ['quote' => $quote]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Q-PUBLIC-1')
            ->assertSee('Public Quote Client')
            ->assertSee('Office deep cleaning')
            ->assertSee('Print / Save PDF')
            ->assertSee('content="noindex,nofollow"', false)
            ->assertSee('content="no-referrer"', false)
            ->assertDontSee('Back to document')
            ->assertDontSee('window.setTimeout', false);

        $quote->refresh();
        $this->assertSame('viewed', $quote->status);
        $this->assertNotNull($quote->viewed_at);
    }

    public function test_unsigned_or_modified_public_links_are_rejected(): void
    {
        $quote = $this->quote();

        $this->get(route('quotes.public', $quote))->assertForbidden();
        $url = URL::signedRoute('quotes.public', ['quote' => $quote]);
        $this->get($url.'&changed=1')->assertForbidden();
    }

    public function test_draft_quotation_is_not_public_even_with_a_valid_signature(): void
    {
        $quote = $this->quote('draft');

        $this->get(URL::signedRoute('quotes.public', ['quote' => $quote]))->assertNotFound();
    }

    public function test_admin_page_provides_the_stable_public_link_for_a_final_quotation(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $quote = $this->quote('accepted');
        $url = URL::signedRoute('quotes.public', ['quote' => $quote]);

        $this->actingAs($admin)->get(route('admin.quotes.show', $quote))
            ->assertOk()
            ->assertSee('Public quotation permalink')
            ->assertSee($url, false)
            ->assertSee('data-copy-text', false);
    }
}
