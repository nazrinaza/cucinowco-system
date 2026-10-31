<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('introducer_source_id')->nullable()->constrained('referral_sources')->nullOnDelete();
            $table->string('introducer_code', 80)->default('direct')->index();
            $table->string('introducer_name', 120)->nullable();
            $table->string('introducer_channel', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('introducer_source_id');
            $table->dropColumn(['introducer_code', 'introducer_name', 'introducer_channel']);
        });
    }
};
