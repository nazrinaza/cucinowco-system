<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('role', 30)->default('admin')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->index();
            $table->string('type', 30)->default('residential');
            $table->string('company_name')->nullable();
            $table->text('address')->nullable();
            $table->string('postcode', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('name_ms')->nullable();
            $table->string('category', 80)->nullable();
            $table->text('description')->nullable();
            $table->string('unit', 30)->default('job');
            $table->decimal('base_price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->string('role', 60)->default('cleaner');
            $table->string('status', 30)->default('available');
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('completed_jobs')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number', 40)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('source', 40)->default('website');
            $table->string('status', 30)->default('draft')->index();
            $table->string('property_type', 40)->nullable();
            $table->date('preferred_date')->nullable();
            $table->string('preferred_time_slot', 40)->nullable();
            $table->string('frequency', 30)->default('one_off');
            $table->text('service_address')->nullable();
            $table->string('postcode', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 30)->default('job');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number', 40)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->dateTime('scheduled_start')->nullable()->index();
            $table->dateTime('scheduled_end')->nullable();
            $table->text('service_address');
            $table->text('access_notes')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 40)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable()->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('payment_terms', 120)->default('Due within 14 days');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 30)->default('job');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 40)->unique();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('method', 40);
            $table->string('status', 30)->default('completed');
            $table->decimal('amount', 12, 2);
            $table->dateTime('paid_at');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('status', 30)->default('subscribed')->index();
            $table->string('source', 50)->default('website');
            $table->json('segments')->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->string('preview_text')->nullable();
            $table->longText('content');
            $table->string('status', 30)->default('draft')->index();
            $table->json('segments')->nullable();
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->dateTime('sent_at')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('click_count')->default(0);
            $table->unsignedInteger('bounce_count')->default(0);
            $table->unsignedInteger('unsubscribe_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
        Schema::dropIfExists('subscribers');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('services');
        Schema::dropIfExists('customers');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'role', 'is_active', 'last_login_at']);
        });
    }
};
