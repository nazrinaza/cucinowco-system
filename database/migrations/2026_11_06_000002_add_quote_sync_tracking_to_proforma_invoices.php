<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->timestamp('synced_from_quote_at')->nullable()->after('sent_at');
            $table->foreignId('synced_by_user_id')->nullable()->after('sent_by_user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('synced_by_user_id');
            $table->dropColumn('synced_from_quote_at');
        });
    }
};
