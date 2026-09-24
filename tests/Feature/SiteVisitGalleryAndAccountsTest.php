<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\SiteVisitRequest;
use App\Models\SiteVisitPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SiteVisitGalleryAndAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function visitWithBooking(): array
    {
        $customer = Customer::create(['name' => 'Gallery Client', 'phone' => '01112345678']);
        $quote = Quote::create(['quote_number' => 'Q-GALLERY-1', 'customer_id' => $customer->id, 'status' => 'accepted']);
        $visit = SiteVisitRequest::create([
            'reference_number' => 'SV-GALLERY-1', 'customer_id' => $customer->id,
            'quote_id' => $quote->id, 'status' => 'completed', 'space_type' => 'office',
            'site_address' => 'Petaling Jaya',
        ]);
        $booking = Booking::create([
            'booking_number' => 'BK-GALLERY-1', 'customer_id' => $customer->id,
            'quote_id' => $quote->id, 'status' => 'confirmed', 'service_address' => 'Petaling Jaya',
        ]);

        return [$visit, $booking];
    }

    public function test_private_before_and_after_uploads_record_the_user_and_gate_completion(): void
    {
        Storage::fake('local');
        [$visit, $booking] = $this->visitWithBooking();
        $field = User::factory()->create(['role' => 'field']);

        $this->actingAs($field)->patch(route('admin.bookings.update', $booking), ['status' => 'completed'])
            ->assertSessionHasErrors('status');
        $this->actingAs($field)->post(route('admin.site-visits.photos.store', $visit), [
            'phase' => 'after', 'photo' => UploadedFile::fake()->image('after.jpg'),
        ])->assertSessionHasErrors('photo');

        $this->actingAs($field)->post(route('admin.site-visits.photos.store', $visit), [
            'phase' => 'before', 'caption' => 'Main hall', 'photo' => UploadedFile::fake()->image('before.jpg'),
        ])->assertSessionHasNoErrors();
        $before = $visit->photos()->firstOrFail();
        $this->assertSame($field->id, $before->uploaded_by_user_id);
        Storage::disk('local')->assertExists($before->path);
        $this->get(route('admin.site-visits.photos.show', [$visit, $before]))->assertOk();
        auth()->logout();
        $this->get(route('admin.site-visits.photos.show', [$visit, $before]))->assertRedirect(route('login'));

        $this->actingAs($field)->patch(route('admin.bookings.update', $booking), ['status' => 'in_progress'])
            ->assertSessionHasNoErrors();
        $this->actingAs($field)->patch(route('admin.bookings.update', $booking), ['status' => 'completed'])
            ->assertSessionHasErrors('status');
        $this->actingAs($field)->post(route('admin.site-visits.photos.store', $visit), [
            'phase' => 'after', 'photo' => UploadedFile::fake()->image('after.jpg'),
        ])->assertSessionHasNoErrors();
        $this->actingAs($field)->patch(route('admin.bookings.update', $booking), ['status' => 'completed'])
            ->assertSessionHasNoErrors();
        $this->assertSame('completed', $booking->fresh()->status);
    }

    public function test_photo_foreign_keys_are_integers_even_when_the_database_returns_strings(): void
    {
        $photo = new SiteVisitPhoto;
        $photo->setRawAttributes(['site_visit_request_id' => '4', 'uploaded_by_user_id' => '7']);

        $this->assertSame(4, $photo->site_visit_request_id);
        $this->assertSame(7, $photo->uploaded_by_user_id);
    }

    public function test_only_admin_manages_accounts_and_field_cannot_open_financial_documents(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $field = User::factory()->create(['role' => 'field']);

        $this->actingAs($field)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($field)->get(route('admin.quotes.index'))->assertForbidden();
        $this->actingAs($field)->get(route('admin.site-visits.index'))->assertOk();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Nur Field', 'email' => 'nur@example.com', 'role' => 'field',
            'password' => 'a-long-unique-password', 'password_confirmation' => 'a-long-unique-password',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'nur@example.com', 'role' => 'field', 'is_active' => true]);

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'field', 'is_active' => 0,
        ])->assertSessionHasErrors('role');
    }

    public function test_quotes_and_invoices_record_who_created_and_emailed_them(): void
    {
        Mail::fake();
        $office = User::factory()->create(['role' => 'office']);
        $customer = Customer::create(['name' => 'Document Client', 'phone' => '01112345678', 'email' => 'client@example.com']);
        $quote = Quote::create([
            'quote_number' => 'Q-AUDIT-1', 'customer_id' => $customer->id,
            'status' => 'draft', 'subtotal' => 100, 'total' => 100,
        ]);
        $quote->items()->create(['description' => 'Deep Clean', 'quantity' => 1, 'unit' => 'job', 'unit_price' => 100, 'amount' => 100]);

        $this->actingAs($office)->post(route('admin.quotes.send', $quote))->assertSessionHasNoErrors();
        $this->assertSame($office->id, $quote->fresh()->sent_by_user_id);

        $this->actingAs($office)->post(route('admin.quotes.convert', $quote))->assertRedirect();
        $invoice = $quote->fresh()->invoice;
        $this->assertSame($office->id, $invoice->created_by_user_id);

        $this->actingAs($office)->post(route('admin.invoices.send', $invoice))->assertSessionHasNoErrors();
        $this->assertSame($office->id, $invoice->fresh()->sent_by_user_id);
    }
}
