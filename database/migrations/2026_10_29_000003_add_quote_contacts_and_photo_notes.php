<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->json('contact_people')->nullable()->after('customer_notes');
        });

        Schema::table('site_visit_photos', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('caption');
        });
    }

    public function down(): void
    {
        Schema::table('site_visit_photos', fn (Blueprint $table) => $table->dropColumn('notes'));
        Schema::table('quotes', fn (Blueprint $table) => $table->dropColumn('contact_people'));
    }
};
