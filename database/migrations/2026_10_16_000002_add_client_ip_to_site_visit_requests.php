<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visit_requests', function (Blueprint $table) {
            $table->string('client_ip', 45)->nullable()->after('referral_landed_at');
        });
    }

    public function down(): void
    {
        Schema::table('site_visit_requests', function (Blueprint $table) {
            $table->dropColumn('client_ip');
        });
    }
};
