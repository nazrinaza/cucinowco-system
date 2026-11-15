<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Programme-wide settings HQ can change without a deploy (payout rules, customer discount).
        Schema::create('agent_programme_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->string('value', 255)->nullable();
            $table->timestamps();
        });
        DB::table('agent_programme_settings')->insert([
            ['key' => 'payout_minimum', 'value' => '50.00', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'payout_days', 'value' => '10,28', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'customer_discount_percent', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('agent_media_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->foreignId('parent_id')->nullable()->constrained('agent_media_folders')->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
        Schema::table('agent_media', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('id')->constrained('agent_media_folders')->nullOnDelete();
        });

        Schema::table('site_visit_requests', function (Blueprint $table) {
            $table->string('promo_code', 80)->nullable();
            $table->decimal('promo_discount_percent', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_visit_requests', function (Blueprint $table) {
            $table->dropColumn(['promo_code', 'promo_discount_percent']);
        });
        Schema::table('agent_media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });
        Schema::dropIfExists('agent_media_folders');
        Schema::dropIfExists('agent_programme_settings');
    }
};
