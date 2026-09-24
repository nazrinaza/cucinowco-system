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
        ])->assertSessionHasErrors('before_photo_id');

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
            'phase' => 'after', 'before_photo_id' => $before->id,
            'photo' => UploadedFile::fake()->image('after.jpg'),
        ])->assertSessionHasNoErrors();
        $this->assertSame($before->id, $visit->photos()->where('phase', 'after')->firstOrFail()->before_photo_id);
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

    public function test_large_png_is_resized_and_an_after_photo_must_reference_the_same_visit(): void
    {
        Storage::fake('local');
        [$visit] = $this->visitWithBooking();
        $otherCustomer = Customer::create(['name' => 'Other Client', 'phone' => '01199999999']);
        $otherVisit = SiteVisitRequest::create([
            'reference_number' => 'SV-GALLERY-2', 'customer_id' => $otherCustomer->id,
            'status' => 'new', 'space_type' => 'office', 'site_address' => '',
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.site-visits.photos.store', $visit), [
            'phase' => 'before', 'caption' => 'Entrance towards stage',
            'photo' => UploadedFile::fake()->image('large.png', 3000, 1800),
        ])->assertSessionHasNoErrors();
        $before = $visit->photos()->firstOrFail();
        $this->assertSame('image/jpeg', $before->mime_type);
        $this->assertLessThanOrEqual(2000, max(getimagesize(Storage::disk('local')->path($before->path))[0], getimagesize(Storage::disk('local')->path($before->path))[1]));

        $this->actingAs($admin)->post(route('admin.site-visits.photos.store', $otherVisit), [
            'phase' => 'after', 'before_photo_id' => $before->id,
            'photo' => UploadedFile::fake()->image('after.jpg'),
        ])->assertSessionHasErrors('before_photo_id');
        $this->assertDatabaseCount('site_visit_photos', 1);
    }

    public function test_heic_upload_is_preserved_and_oversized_files_are_rejected(): void
    {
        Storage::fake('local');
        [$visit] = $this->visitWithBooking();
        $admin = User::factory()->create();
        $heic = pack('N', 24).'ftypheic'.str_repeat("\0", 12);

        $this->actingAs($admin)->post(route('admin.site-visits.photos.store', $visit), [
            'phase' => 'before', 'caption' => 'Front doorway',
            'photo' => UploadedFile::fake()->createWithContent('camera.heic', $heic),
        ])->assertSessionHasNoErrors();
        $photo = $visit->photos()->firstOrFail();
        $this->assertSame('image/heic', $photo->mime_type);
        $this->assertStringEndsWith('.heic', $photo->path);
        Storage::disk('local')->assertExists($photo->path);

        $this->actingAs($admin)->post(route('admin.site-visits.photos.store', $visit), [
            'phase' => 'before', 'caption' => 'Side doorway',
            'photo' => UploadedFile::fake()->create('oversized.jpg', 8193, 'image/jpeg'),
        ])->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('site_visit_photos', 1);
    }

    public function test_an_earlier_after_photo_can_be_paired_and_its_reference_cannot_then_be_deleted(): void
    {
        Storage::fake('local');
        [$visit, $booking] = $this->visitWithBooking();
        $admin = User::factory()->create();
        $before = $visit->photos()->create([
            'phase' => 'before', 'path' => 'before.jpg', 'mime_type' => 'image/jpeg',
            'original_name' => 'before.jpg', 'caption' => 'Doorway facing desk', 'uploaded_by_user_id' => $admin->id,
        ]);
        $after = $visit->photos()->create([
            'phase' => 'after', 'path' => 'after.jpg', 'mime_type' => 'image/jpeg',
            'original_name' => 'after.jpg', 'uploaded_by_user_id' => $admin->id,
        ]);
        Storage::disk('local')->put('before.jpg', 'before');
        Storage::disk('local')->put('after.jpg', 'after');

        $this->actingAs($admin)->patch(route('admin.bookings.update', $booking), ['status' => 'completed'])
            ->assertSessionHasErrors('status');
        $this->actingAs($admin)->get(route('admin.site-visits.show', $visit))
            ->assertOk()->assertSee('Doorway facing desk')->assertSee('Earlier after photos to pair');
        $this->actingAs($admin)->patch(route('admin.site-visits.photos.pair', [$visit, $after]), [
            'before_photo_id' => $before->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($before->id, $after->fresh()->before_photo_id);

        $this->actingAs($admin)->delete(route('admin.site-visits.photos.destroy', [$visit, $before]))
            ->assertSessionHasErrors('photo');
        Storage::disk('local')->assertExists('before.jpg');
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
