<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('google_email')->nullable();
            $table->string('calendar_id');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_calendar_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('google_event_id')->nullable()->unique();
            $table->string('status', 30)->default('pending')->index();
            $table->string('last_synced_fingerprint', 64)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::table('site_visit_requests', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('quote_id')->constrained('staff')->nullOnDelete();
            $table->dateTime('scheduled_start')->nullable()->after('preferred_time_slot')->index();
            $table->dateTime('scheduled_end')->nullable()->after('scheduled_start');
        });

        Schema::create('site_visit_calendar_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_visit_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('google_event_id')->nullable()->unique();
            $table->string('status', 30)->default('pending')->index();
            $table->string('last_synced_fingerprint', 64)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visit_calendar_syncs');
        Schema::table('site_visit_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
            $table->dropIndex(['scheduled_start']);
            $table->dropColumn(['scheduled_start', 'scheduled_end']);
        });
        Schema::dropIfExists('booking_calendar_syncs');
        Schema::dropIfExists('google_calendar_connections');
    }
};
