<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visit_photos', function (Blueprint $table) {
            $table->foreignId('before_photo_id')->nullable()->after('phase')
                ->constrained('site_visit_photos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_visit_photos', fn (Blueprint $table) => $table->dropConstrainedForeignId('before_photo_id'));
    }
};
