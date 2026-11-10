<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 40)->unique();
            $table->string('name', 120);
            $table->string('phone', 30)->index();
            $table->string('email', 160)->nullable();
            $table->text('message');
            $table->boolean('whatsapp_consent')->default(false);
            $table->string('status', 30)->default('new')->index();
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('provider_status', 30)->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->string('referral_code', 100)->default('direct')->index();
            $table->string('landing_url', 2048)->nullable();
            $table->timestamp('submitted_to_meta_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_enquiries');
    }
};
