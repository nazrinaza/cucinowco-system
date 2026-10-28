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

    public function test_private_inspection_gallery_records_the_uploader_without_gating_job_completion(): void
    {
        Storage::fake('local');
        [$visit, $booking] = $this->visitWithBooking();
        $field = User::factory()->create(['role' => 'field']);

        $this->actingAs($field)->post(route('admin.site-visits.photos.store', $visit), [
            'caption' => 'Main hall access', 'photo' => UploadedFile::fake()->image('inspection.jpg'),
        ])->assertSessionHasNoErrors();
        $photo = $visit->photos()->firstOrFail();
        $this->assertSame('inspection', $photo->phase);
        $this->assertSame($field->id, $photo->uploaded_by_user_id);
        Storage::disk('local')->assertExists($photo->path);
        $this->get(route('admin.site-visits.photos.show', [$visit, $photo]))->assertOk();
        auth()->logout();
        $this->get(route('admin.site-visits.photos.show', [$visit, $photo]))->assertRedirect(route('login'));

        $this->actingAs($field)->patch(route('admin.bookings.update', $booking), ['status' => 'in_progress'])
            ->assertSessionHasNoErrors();
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

    public function test_large_png_is_resized_for_the_inspection_gallery(): void
    {
        Storage::fake('local');
        [$visit] = $this->visitWithBooking();
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.site-visits.photos.store', $visit), [
            'caption' => 'Entrance towards stage',
            'photo' => UploadedFile::fake()->image('large.png', 3000, 1800),
        ])->assertSessionHasNoErrors();
        $photo = $visit->photos()->firstOrFail();
        $this->assertSame('image/jpeg', $photo->mime_type);
        $this->assertLessThanOrEqual(2000, max(getimagesize(Storage::disk('local')->path($photo->path))[0], getimagesize(Storage::disk('local')->path($photo->path))[1]));

        $this->assertDatabaseCount('site_visit_photos', 1);
    }

    public function test_heic_upload_is_preserved_and_oversized_files_are_rejected(): void
    {
        Storage::fake('local');
        [$visit] = $this->visitWithBooking();
        $admin = User::factory()->create();
        $heic = pack('N', 24).'ftypheic'.str_repeat("\0", 12);

        $this->actingAs($admin)->post(route('admin.site-visits.photos.store', $visit), [
            'caption' => 'Front doorway',
            'photo' => UploadedFile::fake()->createWithContent('camera.heic', $heic),
        ])->assertSessionHasNoErrors();
        $photo = $visit->photos()->firstOrFail();
        $this->assertSame('image/heic', $photo->mime_type);
        $this->assertStringEndsWith('.heic', $photo->path);
        Storage::disk('local')->assertExists($photo->path);

        $this->actingAs($admin)->post(route('admin.site-visits.photos.store', $visit), [
            'caption' => 'Side doorway',
            'photo' => UploadedFile::fake()->create('oversized.jpg', 8193, 'image/jpeg'),
        ])->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('site_visit_photos', 1);
    }

    public function test_team_can_save_and_complete_a_structured_site_assessment(): void
    {
        [$visit] = $this->visitWithBooking();
        $field = User::factory()->create(['role' => 'field']);

        $this->actingAs($field)->patch(route('admin.site-visits.assessment.update', $visit), [
            'assessment_state' => 'draft',
            'site_size_sqft' => 12000,
            'tools' => ['floor_scrubber', 'wet_dry_vacuum'],
            'ppe' => ['gloves', 'safety_boots'],
            'forklift_required' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertNull($visit->fresh()->assessment_completed_at);

        $this->actingAs($field)->patch(route('admin.site-visits.assessment.update', $visit), [
            'assessment_state' => 'completed',
            'discovery_source' => 'referral_agent',
            'site_size_sqft' => 12000,
            'floor_count' => 2,
            'premises_type' => 'factory_warehouse',
            'tools' => ['floor_scrubber', 'wet_dry_vacuum'],
            'other_tools' => 'Bring spare squeegee blades',
            'ppe' => ['gloves', 'safety_boots'],
            'safety_signage_required' => 1,
            'forklift_required' => 1,
            'expected_duration_hours' => 12.5,
            'access_notes' => 'Use loading bay B.',
        ])->assertSessionHasNoErrors();

        $visit->refresh();
        $this->assertSame($field->id, $visit->assessment_completed_by_user_id);
        $this->assertNotNull($visit->assessment_completed_at);
        $this->assertSame(12000, $visit->assessment_data['site_size_sqft']);
        $this->assertTrue($visit->assessment_data['forklift_required']);
        $this->assertSame(['floor_scrubber', 'wet_dry_vacuum'], $visit->assessment_data['tools']);

        $this->actingAs($field)->get(route('admin.site-visits.show', $visit))
            ->assertOk()->assertSee('Site assessment checklist')->assertSee('Inspection photo gallery');
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
