<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('bank_account_name', 160)
                ->default('Thursina Cleaning & Services')
                ->after('payment_terms');
            $table->string('bank_account_number', 80)
                ->default('8606139534')
                ->after('bank_account_name');
            $table->string('bank_name', 120)
                ->default('CIMB Bank')
                ->after('bank_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['bank_account_name', 'bank_account_number', 'bank_name']);
        });
    }
};
