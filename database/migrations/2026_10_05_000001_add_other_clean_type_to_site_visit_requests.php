<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visit_requests', function (Blueprint $table) {
            $table->string('other_clean_type', 180)->nullable()->after('clean_types');
        });
    }

    public function down(): void
    {
        Schema::table('site_visit_requests', function (Blueprint $table) {
            $table->dropColumn('other_clean_type');
        });
    }
};
