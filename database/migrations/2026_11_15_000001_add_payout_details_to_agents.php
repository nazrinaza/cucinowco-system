<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('payout_bank', 40)->nullable();
            $table->string('payout_account_name', 120)->nullable();
            // Encrypted with APP_KEY, so it needs room for the ciphertext.
            $table->text('payout_account_number')->nullable();
            $table->timestamp('payout_details_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['payout_bank', 'payout_account_name', 'payout_account_number', 'payout_details_updated_at']);
        });
    }
};
