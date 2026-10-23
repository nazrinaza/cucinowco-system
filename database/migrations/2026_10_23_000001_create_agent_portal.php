<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 160)->unique();
            $table->string('phone', 30);
            $table->string('location', 120)->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('requested_code', 80)->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('referral_source_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->json('social_links')->nullable();
            $table->text('hq_notes')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('terms_accepted_at');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
        Schema::create('agent_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('agent_media', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('category', 40);
            $table->text('description')->nullable();
            $table->text('caption')->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('referral_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_source_id')->constrained()->cascadeOnDelete();
            $table->string('session_hash', 64);
            $table->string('channel', 30)->default('link');
            $table->string('landing_path', 255);
            $table->string('origin_host', 255)->nullable();
            $table->string('device', 20)->default('desktop');
            $table->timestamp('created_at')->index();
            $table->unique(['referral_source_id', 'session_hash']);
            $table->index(['referral_source_id', 'created_at']);
        });
        Schema::create('agent_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_activities');
        Schema::dropIfExists('referral_visits');
        Schema::dropIfExists('agent_media');
        Schema::dropIfExists('agent_password_reset_tokens');
        Schema::dropIfExists('agents');
    }
};
