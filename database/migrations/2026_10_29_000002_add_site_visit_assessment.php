<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visit_requests', function (Blueprint $table) {
            $table->json('assessment_data')->nullable()->after('internal_notes');
            $table->timestamp('assessment_completed_at')->nullable()->after('assessment_data');
            $table->foreignId('assessment_completed_by_user_id')->nullable()->after('assessment_completed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_visit_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assessment_completed_by_user_id');
            $table->dropColumn(['assessment_data', 'assessment_completed_at']);
        });
    }
};
