<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 80)->unique();
            $table->string('channel', 40)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('visit_count')->default(0);
            $table->unsignedBigInteger('lead_count')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('site_visit_requests', function (Blueprint $table) {
            $table->foreignId('referral_source_id')->nullable()->after('source')->constrained()->nullOnDelete();
            $table->string('referral_code', 80)->default('direct')->after('referral_source_id')->index();
            $table->text('landing_url')->nullable()->after('referral_code');
            $table->timestamp('referral_landed_at')->nullable()->after('landing_url');
        });
    }

    public function down(): void
    {
        Schema::table('site_visit_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_source_id');
            $table->dropColumn(['referral_code', 'landing_url', 'referral_landed_at']);
        });

        Schema::dropIfExists('referral_sources');
    }
};
