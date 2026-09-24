<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_visit_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_visit_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phase', 10)->index();
            $table->string('path');
            $table->string('mime_type', 80);
            $table->string('original_name');
            $table->string('caption', 180)->nullable();
            $table->timestamps();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('recorded_by_user_id'));
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('last_edited_by_user_id');
            $table->dropConstrainedForeignId('sent_by_user_id');
        });
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('last_edited_by_user_id');
            $table->dropConstrainedForeignId('sent_by_user_id');
        });
        Schema::dropIfExists('site_visit_photos');
    }
};
