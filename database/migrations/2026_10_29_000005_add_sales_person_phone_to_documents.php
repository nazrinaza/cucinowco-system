<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('sales_person_phone', 30)->nullable()->after('sales_person_title');
        });

        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->string('sales_person_phone', 30)->nullable()->after('sales_person_title');
        });

        DB::table('quotes')->whereNotNull('sales_person_user_id')->orderBy('id')->chunkById(100, function ($quotes): void {
            $phones = DB::table('users')->whereIn('id', $quotes->pluck('sales_person_user_id')->filter()->unique())->pluck('phone', 'id');
            foreach ($quotes as $quote) {
                if (filled($phones[$quote->sales_person_user_id] ?? null)) {
                    DB::table('quotes')->where('id', $quote->id)->update(['sales_person_phone' => $phones[$quote->sales_person_user_id]]);
                }
            }
        });

        DB::table('proforma_invoices')->orderBy('id')->chunkById(100, function ($proformas): void {
            $phones = DB::table('quotes')->whereIn('id', $proformas->pluck('quote_id'))->pluck('sales_person_phone', 'id');
            foreach ($proformas as $proforma) {
                if (filled($phones[$proforma->quote_id] ?? null)) {
                    DB::table('proforma_invoices')->where('id', $proforma->id)->update(['sales_person_phone' => $phones[$proforma->quote_id]]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('proforma_invoices', fn (Blueprint $table) => $table->dropColumn('sales_person_phone'));
        Schema::table('quotes', fn (Blueprint $table) => $table->dropColumn('sales_person_phone'));
    }
};
